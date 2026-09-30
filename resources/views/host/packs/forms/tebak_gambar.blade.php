{{-- Tebak Gambar question: title, the answer for hosts, points and two images (E8, E9). Used by the
     pack form and by the edit form of an unplayed copy (D-2). --}}
@inject('mediaUrls', 'App\Media\MediaUrls')
@php
    $detail = $question?->gambar;
    $questionImage = $detail?->questionImage;
    $answerImage = $detail?->answerImage;
    $points = (string) old('points', $question?->points ?? 1);
@endphp
<x-field name="title" :label="__('packs.gambar.title')" :value="old('title', $detail?->title)" maxlength="200" autocomplete="off" />
<x-field name="answer_text" :label="__('packs.gambar.answer')" :value="old('answer_text', $detail?->answer_text)" :hint="__('packs.gambar.answer_hint')"
    maxlength="100" autocomplete="off" />

@include('host.packs.forms.image', [
    'field' => 'question_image',
    'label' => __('packs.gambar.question_image'),
    'hint' => __('packs.gambar.question_image_hint'),
    'current' => $questionImage ? $mediaUrls->sizes($questionImage)['small'] : null,
])
@include('host.packs.forms.image', [
    'field' => 'answer_image',
    'label' => __('packs.gambar.answer_image'),
    'hint' => __('packs.gambar.answer_image_hint'),
    // E9: hosts see the private answer image through a signed URL.
    'current' => $answerImage ? $mediaUrls->sizes($answerImage)['small'] : null,
])

@include('host.packs.forms.points', ['points' => $points])
