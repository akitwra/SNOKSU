@extends('layouts.app')
@section('title','Контакты')
@section('content')
<x-page-head title="Будем на связи" eyebrow="КОНТАКТЫ СНО КГУ" description="Вопросы о научном обществе, конференциях и первом исследовании — здесь можно найти, к кому обратиться."/>
<div class="container page-content">
    <div class="section-heading"><div><span class="eyebrow">ЛЮДИ СООБЩЕСТВА</span><h2>Представители СНО</h2></div><a href="#contact-question" class="text-link">Задать вопрос <x-icon name="arrow"/></a></div>
    <div class="contacts-grid">
        <article class="representative-card">
            <div class="representative-heading"><div class="representative-avatar" aria-hidden="true">{{ $primary['initials'] }}</div><div><span class="overline">СНО КГУ</span><h2>{{ $primary['name'] }}</h2><p>{{ $primary['position'] }}</p></div></div>
            <dl class="representative-details"><div><dt>Телефон</dt><dd><a href="tel:{{ $primary['phone_uri'] }}">{{ $primary['phone'] }}</a></dd></div><div><dt>Электронная почта</dt><dd><a href="mailto:{{ $primary['email'] }}">{{ $primary['email'] }}</a></dd></div></dl>
            <a href="mailto:{{ $primary['email'] }}" class="button button-primary"><x-icon name="mail"/> Написать письмо</a>
        </article>
        <article class="representative-card representative-example">
            <div class="representative-heading"><div class="representative-avatar" aria-hidden="true">{{ $example['initials'] }}</div><div><span class="badge">Пример профиля</span><h2>{{ $example['name'] }}</h2><p>{{ $example['position'] }}</p></div></div>
            <p class="representative-note">Пример карточки второго представителя. Имя и роль условные; подтверждённые контакты будут добавлены после согласования.</p>
            <a href="#contact-question" class="button button-outline">Задать вопрос СНО <x-icon name="arrow"/></a>
        </article>
    </div>
    <div class="contacts-address"><span class="eyebrow">КУРСКИЙ ГОСУДАРСТВЕННЫЙ УНИВЕРСИТЕТ</span><p>{{ config('contacts.address') }}</p><a href="https://kursksu.ru" class="text-link" target="_blank" rel="noopener noreferrer">Сайт университета <x-icon name="up-right"/></a></div>
    <section id="contact-question" class="conference-contact split-layout"><div><span class="eyebrow">СПРОСИ У СНО</span><h2>Остался вопрос?</h2><p>Напиши о конференции, исследовании или участии в обществе. Регистрация не нужна.</p></div><div class="contact-card"><h3>Вопрос представителям СНО</h3><x-question-form id="contacts-question"/></div></section>
</div>
@endsection
