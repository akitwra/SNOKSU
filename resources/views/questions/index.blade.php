@extends('layouts.app')
@section('title','Вопросы представителям СНО')
@section('content')
<x-page-head title="Вопросы представителям СНО" eyebrow="УПРАВЛЕНИЕ" description="Обращения посетителей без регистрации. Ответьте на указанную отправителем почту."/>
<div class="container narrow page-content">@forelse($questions as $question)<article class="panel question-entry"><span class="overline">{{ $question->topic }} · {{ $question->created_at->format('d.m.Y H:i') }} МСК</span><h2>{{ $question->name }}</h2><a href="mailto:{{ $question->email }}" class="text-link">{{ $question->email }}</a><p class="question-message">{{ $question->message }}</p></article>@empty<x-empty title="Новых вопросов пока нет" text="Здесь появятся обращения из публичных форм сайта."/>@endforelse{{ $questions->links() }}</div>
@endsection
