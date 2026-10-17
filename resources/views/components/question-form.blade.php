@props(['id' => 'sno-question', 'topic' => 'Общий вопрос'])
@php($googleForm = config('society.questions_form_url'))
@if($googleForm && preg_match('#^https://(docs\.google\.com/forms/|forms\.gle/)#', $googleForm))
<div class="question-form"><p>Задай вопрос представителям СНО через Google Форму. Ответ будет направлен по контактам, которые ты в ней укажешь.</p><a href="{{ $googleForm }}" target="_blank" rel="noopener noreferrer" class="button button-primary" style="margin-top:20px">Задать вопрос в Google Форме <x-icon name="up-right"/></a></div>
@else
<form action="/questions" method="POST" class="question-form" data-question-form>
    @csrf
    <div class="form-grid">
        <div class="field"><label for="{{ $id }}-name">Как тебя зовут?</label><input id="{{ $id }}-name" name="name" required maxlength="120" autocomplete="name" value="{{ old('name') }}"></div>
        <div class="field"><label for="{{ $id }}-email">Почта для ответа</label><input id="{{ $id }}-email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ old('email') }}"></div>
    </div>
    <div class="field"><label for="{{ $id }}-topic">Тема обращения</label><select id="{{ $id }}-topic" name="topic">@foreach(config('society.question_topics') as $option)<option @selected(old('topic', $topic)===$option)>{{ $option }}</option>@endforeach</select></div>
    <div class="field"><label for="{{ $id }}-message">Твой вопрос</label><textarea id="{{ $id }}-message" name="message" required minlength="10" maxlength="4000" rows="5" placeholder="Расскажи, что хочется уточнить">{{ old('message') }}</textarea></div>
    <div class="question-trap" aria-hidden="true"><label for="{{ $id }}-website">Оставьте поле пустым</label><input id="{{ $id }}-website" name="website" tabindex="-1" autocomplete="off"></div>
    <label class="checkbox"><input type="checkbox" name="consent" value="1" required>Согласен на обработку имени, почты и текста вопроса для ответа представителей СНО.</label>
    <p class="hint question-delivery" data-question-status role="status">Регистрация не нужна. Представители СНО используют указанную почту для ответа на вопрос.</p>
    <button type="submit" class="button button-primary">Отправить вопрос <x-icon name="arrow"/></button>
</form>

@endif
