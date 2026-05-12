<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Section;

class SectionController extends Controller
{
    public function hierarchy()
    {
        // استدعاء جميع الشُعب مع فئاتها وإجراءاتها بشكل مهيكل
        $sections = Section::with(['categories.actions'])->get();
        return response()->json($sections);
    }
}
