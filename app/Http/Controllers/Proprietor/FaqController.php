<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFaqRequest;
use App\Http\Requests\UpdateFaqRequest;
use App\Models\Faq;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::with('creator')
            ->latest()
            ->paginate(10);

        return view('proprietor.faqs.index', compact('faqs'));
    }

    public function create()
    {
        return view('proprietor.faqs.create');
    }

    public function store(StoreFaqRequest $request)
    {
        Faq::create([
            'created_by' => auth()->id(),
            'question' => $request->validated('question'),
            'answer' => $request->validated('answer'),
        ]);

        return redirect()
            ->route('proprietor.faqs.index')
            ->with('success', 'FAQ created successfully.');
    }

    public function edit(Faq $faq)
    {
        return view('proprietor.faqs.edit', compact('faq'));
    }

    public function update(UpdateFaqRequest $request, Faq $faq)
    {
        $faq->update($request->validated());

        return redirect()
            ->route('proprietor.faqs.index')
            ->with('success', 'FAQ updated successfully.');
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();

        return redirect()
            ->route('proprietor.faqs.index')
            ->with('success', 'FAQ deleted successfully.');
    }
}
