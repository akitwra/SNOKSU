@extends('layouts.app')
@section('title','Задать вопрос СНО')
@section('content')
<x-page-head title="Спросить — тоже первый шаг" eyebrow="НА СВЯЗИ С СНО" description="Спроси о конференции, исследовании или участии в обществе. Просто заполни форму — без регистрации."/>
<div class="container page-content split-layout"><section class="contact-card"><h2>Вопрос представителям СНО</h2><x-question-form id="contact-question"/></section><aside class="panel"><h3>О чём можно спросить?</h3><p>Как выбрать конференцию, подготовить выступление, найти наставника или присоединиться к СНО.</p><p style="margin-top:18px">Укажи почту, на которую удобно получить ответ. Не добавляй в сообщение документы и другие лишние персональные сведения.</p></aside></div>
@endsection
