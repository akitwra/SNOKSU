<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function create()
    {
        return view('questions.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190',
            'topic' => ['required', Rule::in(config('society.question_topics'))],
            'message' => 'required|string|min:10|max:4000',
            'consent' => 'accepted',
            'website' => 'nullable|size:0',
        ]);
        DB::transaction(function () use ($data) {
            Question::create(collect($data)->only(['name', 'email', 'topic', 'message'])->all());
            foreach (User::where('role', 'organizer')->get() as $organizer) {
                Notice::create(['user_id' => $organizer->id, 'title' => 'Новый вопрос представителям СНО', 'body' => 'Вопрос по теме «'.$data['topic'].'» ожидает ответа.', 'url' => '/manage/questions']);
            }
        });

        $message = 'Вопрос передан представителям СНО. Ответ придёт на указанную почту после рассмотрения.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 201);
        }

        return back()->with('success', $message);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'organizer', 403);

        return view('questions.index', ['questions' => Question::latest()->paginate(20)]);
    }
}
