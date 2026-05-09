<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Clerk;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UserController extends Controller
{
    // 1. جلب جميع الموظفين (Lister les utilisateurs)
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Accès refusé. Vous n\'êtes pas administrateur.'], 403);
        }
        // On charge les utilisateurs avec leurs relations
        $users = User::with(['clerk', 'admin'])->orderBy('id', 'desc')->get();

        $data = $users->map(function ($user) {
            $isAdmin = $user->admin !== null;

            // 🔥 LA CORRECTION EST ICI : On va chercher le nom/prénom dans la bonne table
            $prenom = $isAdmin ? ($user->admin->prenom ?? '') : ($user->clerk->prenom ?? '');
            $nom = $isAdmin ? ($user->admin->nom ?? '') : ($user->clerk->nom ?? '');

            return [
                'id'            => $user->id,
                'first_name'    => $prenom ?: 'بدون', // Fallback si vide
                'last_name'     => $nom ?: 'اسم',     // Fallback si vide
                'email'         => $user->email,
                'role_type'     => $isAdmin ? 'admin' : 'clerk',
                'role'          => $isAdmin ? 'مدير النظام' : 'كاتب ضبط',
                'status'        => 'نشط',
                'last_login_at' => $user->last_login_at ? \Carbon\Carbon::parse($user->last_login_at)->format('Y-m-d H:i') : '-'// À lier si tu utilises le champ last_activity de la table sessions plus tard
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // 2. إعادة تعيين كلمة المرور (Réinitialiser le mot de passe)
    public function resetPassword(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح لك للقيام بهذه العملية'], 403);
        }

        $user = User::findOrFail($id);

        $newPassword = Str::random(8);

        $user->password = Hash::make($newPassword);
        $user->save();

        return response()->json([
            'success' => true,
            'new_password' => $newPassword
        ]);
    }

    // 3. حذف موظف (Supprimer un utilisateur)
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'غير مصرح لك للقيام بهذه العملية'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'لا يمكنك حذف حسابك الشخصي!'], 400);
        }

        // Le "onDelete('cascade')" dans ta migration va automatiquement supprimer le Clerk ou l'Admin associé !
        $user->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف المستخدم بنجاح']);
    }

    // 4. استيراد كتاب الضبط عبر Excel (Importation Excel)
    // 4. استيراد كتاب الضبط عبر Excel
    public function import(Request $request)
    {
        if (!$request->user()->admin) {
            return response()->json(['message' => 'غير مصرح لك للقيام بهذه العملية'], 403);
        }

        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();

            $map = [
                'first_name' => -1,
                'last_name'  => -1,
                'email'      => -1,
                'type_resp'  => -1, // 🔥 Nouvelle colonne
                'grade'      => -1  // 🔥 Nouvelle colonne
            ];

            $headerFound = false;

            foreach ($rows as $rowIndex => $cells) {
                $cells = array_map(fn($v) => trim((string) $v), $cells);

                if (!$headerFound) {
                    foreach ($cells as $idx => $txt) {
                        if (mb_strpos($txt, 'الاسم الشخصي') !== false) $map['first_name'] = $idx;
                        if (mb_strpos($txt, 'الاسم العائلي') !== false) $map['last_name'] = $idx;
                        if (mb_strpos($txt, 'البريد الإلكتروني') !== false || mb_strpos($txt, 'Email') !== false) $map['email'] = $idx;

                        // 🔥 Détection des nouvelles colonnes
                        if (mb_strpos($txt, 'نوع المسؤولية') !== false || mb_strpos($txt, 'الصفة') !== false) $map['type_resp'] = $idx;
                        if (mb_strpos($txt, 'الدرجة') !== false) $map['grade'] = $idx;
                    }
                    if ($map['email'] !== -1) $headerFound = true;
                    continue;
                }

                if (empty($cells[$map['email']])) continue;

                $email = $cells[$map['email']];
                $firstName = $map['first_name'] !== -1 ? $cells[$map['first_name']] : 'بدون';
                $lastName = $map['last_name'] !== -1 ? $cells[$map['last_name']] : 'اسم';

                // 🔥 Récupération des valeurs (avec un tiret par défaut si la case est vide dans Excel)
                $typeResp = ($map['type_resp'] !== -1 && !empty($cells[$map['type_resp']])) ? $cells[$map['type_resp']] : 'كاتب ضبط';
                $grade = ($map['grade'] !== -1 && !empty($cells[$map['grade']])) ? $cells[$map['grade']] : '-';

                // 1. Création du User
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'password' => Hash::make('12345678'),
                        'role'     => 'clerk'
                    ]
                );

                // 2. Création ou Mise à jour du Clerk
                if (!$user->admin) {
                    Clerk::updateOrCreate(
                        ['user_id' => $user->id], // On cherche par user_id
                        [
                            'prenom'              => $firstName,
                            'nom'                 => $lastName,
                            'type_responsabilite' => $typeResp, // 🔥 On insère la vraie responsabilité
                            'grade'               => $grade     // 🔥 On insère le vrai grade
                        ]
                    );
                }
            }

            return $this->index($request);

        } catch (\Exception $e) {
            return response()->json(['message' => 'خطأ أثناء الاستيراد: ' . $e->getMessage()], 500);
        }
    }
}
