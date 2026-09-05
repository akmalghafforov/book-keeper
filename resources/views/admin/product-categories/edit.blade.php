@extends('layouts.admin')
@section('title', __('Edit Product Category'))
@section('header_title', __('Edit Product Category'))
@section('content')
<div class="max-w-xl mx-auto bg-white dark:bg-[#161615] p-6 rounded shadow"><h2 class="text-2xl font-bold mb-6">{{ __('Edit Product Category') }}</h2><form method="POST" action="{{ route('admin.product-categories.update', $productCategory) }}">@csrf @method('PUT') @include('admin.product-categories.partials.form')<div class="mt-6 flex gap-3"><a href="{{ route('admin.product-categories.index') }}">{{ __('Cancel') }}</a><button class="px-4 py-2 bg-indigo-600 text-white rounded">{{ __('Update') }}</button></div></form></div>
@endsection
