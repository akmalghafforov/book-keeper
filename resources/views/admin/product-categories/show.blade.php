@extends('layouts.admin')
@section('title', __('Product Category Details'))
@section('header_title', __('Product Category Details'))
@section('content')
<div class="max-w-4xl mx-auto space-y-6"><div class="flex justify-between"><h2 class="text-2xl font-bold">{{ $productCategory->name }}</h2><a class="text-indigo-600" href="{{ route('admin.product-categories.edit', $productCategory) }}">{{ __('Edit') }}</a></div><div class="bg-white dark:bg-[#161615] p-6 rounded shadow"><h3 class="font-semibold mb-3">{{ __('Products') }}</h3><ul class="list-disc pl-5">@forelse($productCategory->products as $product)<li><a class="text-indigo-600" href="{{ route('admin.products.show', $product) }}">{{ $product->name }}</a></li>@empty<li>{{ __('No products found.') }}</li>@endforelse</ul></div></div>
@endsection
