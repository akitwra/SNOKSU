@extends('layouts.app')
@section('title',$title)
@section('content')
<x-page-head :title="$title" eyebrow="GITHUB PAGES · ПРЕВЬЮ ДИЗАЙНА" description="Сейчас открыт статический обзор сайта. Авторизация, заявки, исследования и уведомления работают в полноценном Laravel-приложении на PHP-хостинге."/>
<div class="container narrow page-content"><div class="panel"><h2>Как это работает в полной версии</h2><p>Студент выбирает задачу и отправляет заявку. Координатор назначает наставника. После согласования создаётся пространство исследования с этапами, материалами и комментариями.</p><p style="margin-top:20px">Здесь можно посмотреть публичные разделы, подобрать пример задачи, открыть карточки и базу знаний. Вход и отправка данных на GitHub Pages недоступны.</p><div class="detail-meta"><a href="/research" class="button button-primary">Посмотреть задачи <x-icon name="arrow"/></a><a href="/" class="button button-outline">На главную</a><a href="https://github.com/akitwra/SNOKSU" class="button button-outline">Исходный код</a></div></div></div>
@endsection
