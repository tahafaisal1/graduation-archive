<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpecializationRequest;
use App\Http\Requests\UpdateSpecializationRequest;
use App\Models\Specialization;

class SpecializationController extends Controller
{
    public function store(StoreSpecializationRequest $request)
    {
        Specialization::create($request->validated());

        return redirect()->back()
            ->with('success', 'تم إضافة التخصص بنجاح');
    }

    public function update(UpdateSpecializationRequest $request, Specialization $specialization)
    {
        $specialization->update($request->validated());

        return redirect()->back()
            ->with('success', 'تم تحديث التخصص بنجاح');
    }

    public function destroy(Specialization $specialization)
    {
        if ($specialization->projects()->exists()) {
            return back()->with('error', 'لا يمكن حذف التخصص لوجود مشاريع مرتبطة به');
        }

        $specialization->delete();

        return redirect()->back()
            ->with('success', 'تم حذف التخصص بنجاح');
    }
}
