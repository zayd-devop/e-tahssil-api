<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // دالة تسجيل الدخول
    public function login(Request $request)
    {
        // 1. التحقق من صحة البيانات المدخلة
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // 2. التحقق من وجود المستخدم وصحة كلمة المرور
$user = User::with(['clerk', 'admin'])->where('email', $request->identifier ?? $request->email)->first();        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'
            ], 401);
        }
        $user->last_login_at = now();
        $user->save();
        // 3. إنشاء التوكن (Token)
        $token = $user->createToken('auth_token')->plainTextToken;

        // 4. إرجاع النتيجة
        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }

    // دالة تسجيل الخروج
    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        // حذف التوكن الحالي
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        // Plus aucune ligne rouge !
        $token->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }

    // دالة لجلب بيانات المستخدم الحالي (تستخدم للتأكد من حالة الدخول في React)
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}
