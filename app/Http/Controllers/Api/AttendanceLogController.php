<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceLog;
use Illuminate\Http\Request;

class AttendanceLogController extends Controller
{
    public function index() {}

    public function store(Request $request) {}

    public function show(AttendanceLog $attendanceLog) {}

    public function update(Request $request, AttendanceLog $attendanceLog) {}

    public function destroy(AttendanceLog $attendanceLog) {}
}
