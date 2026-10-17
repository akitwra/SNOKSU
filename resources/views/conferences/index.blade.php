@extends('layouts.app')
@section('title', $announcements ? 'Анонс конференций' : 'Наши конференции')
@section('content')
<x-page-head :title="$announcements ? 'Анонс конференций' : 'Наши конференции'" eyebrow="ОБМЕН ИДЕЯМИ · СТУДЕНЧЕСКАЯ НАУКА" :description="$announcements ? 'Предстоящие конференции: даты, формат и подготовка к участию. Выбери событие и задай свой вопрос представителям СНО.' : 'Конференции нашего научного общества: темы, встречи и опыт участников. Здесь можно узнать о событиях и найти ближайшее выступление.'"/>
<div class="container page-content">
    <div class="list-header conference-header"><div class="tabs"><a href="/conferences" @class(['active'=>!$announcements])>Наши конференции</a><a href="/conference-announcements" @class(['active'=>$announcements])>Анонс конференций</a></div><a href="#conference-question" class="text-link">Задать вопрос <x-icon name="arrow"/></a></div>
    <form method="GET" class="search-row conference-search"><div class="search-field"><x-icon name="search"/><input name="q" value="{{ request('q') }}" aria-label="Поиск конференций" placeholder="Название или тема конференции"></div><button type="submit" class="button button-primary">Найти конференцию</button></form>
    <div class="conference-grid" data-conference-list>
        @forelse($conferences as $conference)
        <article class="conference-card" data-conference-id="{{ $conference->id }}"><div class="conference-card-top"><span class="overline">{{ $conference->format }}</span><span class="badge {{ $conference->starts_at->isFuture() ? 'badge-green' : '' }}">{{ $conference->starts_at->isFuture() ? 'Скоро' : 'Состоялась' }}</span></div><time datetime="{{ $conference->starts_at->toIso8601String() }}">{{ $conference->starts_at->translatedFormat('d F Y') }} · {{ $conference->starts_at->format('H:i') }} МСК</time><h2><a href="/events/{{ $conference->id }}">{{ $conference->title }}</a></h2><p>{{ $conference->description }}</p><div class="conference-card-bottom"><a href="/events/{{ $conference->id }}" class="text-link">О конференции <x-icon name="arrow"/></a>@if($conference->is_demo)<span class="hint">Пример, не реальное объявление</span>@endif</div></article>
        @empty
        <x-empty :title="$announcements ? 'Новые анонсы готовятся' : 'Конференции скоро появятся'" text="Представители СНО добавят подтверждённые сведения. Оставь вопрос в форме ниже, если хочешь узнать об участии."/>
        @endforelse
    </div>
    {{ $conferences->links() }}
    <section id="conference-question" class="conference-contact split-layout"><div><span class="eyebrow">СПРОСИ У СНО</span><h2>Хочешь узнать больше?</h2><p>Поможем разобраться с участием, темой доклада и подготовкой. Напиши представителям СНО — без регистрации.</p></div><div class="contact-card"><h3>Вопрос о конференциях</h3><x-question-form id="conference-question-form" topic="Конференции"/></div></section>
</div>
@endsection
