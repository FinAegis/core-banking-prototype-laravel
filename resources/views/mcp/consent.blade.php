@extends('layouts.public')

@section('title', 'Authorize ' . ($client->name ?? 'application'))

@section('seo')
    @include('partials.seo', [
        'title'       => 'Authorize ' . ($client->name ?? 'application') . ' — ' . config('brand.name', 'Zelta'),
        'description' => 'Review and approve the permissions ' . ($client->name ?? 'this application') . ' is requesting on your ' . config('brand.name', 'Zelta') . ' account.',
        'robots'      => 'noindex, nofollow',
    ])
@endsection

@section('content')
<div class="max-w-xl mx-auto py-12 px-4">
  <div class="bg-white rounded-2xl shadow border border-slate-200 p-8">
    <div class="flex items-center gap-4 mb-6">
      @if($client->client_logo_url)
        <img src="{{ $client->client_logo_url }}" alt="" class="w-14 h-14 rounded-xl border border-slate-200" />
      @else
        <div class="w-14 h-14 rounded-xl bg-slate-100 flex items-center justify-center text-2xl">{{ strtoupper(substr($client->name, 0, 1)) }}</div>
      @endif
      <div>
        <div class="text-xs text-slate-500 uppercase tracking-wide">Authorize application</div>
        <h1 class="text-xl font-bold">{{ $client->name }}</h1>
      </div>
    </div>

    <p class="text-slate-700 mb-6">
      <strong>{{ $client->name }}</strong> wants to connect to your {{ config('brand.name', 'Zelta') }} account. Review the permissions below before approving.
    </p>

    <ul class="space-y-3 mb-6">
      @foreach($scopes as $scope)
        <li class="flex items-start gap-3 p-3 rounded-lg {{ $scope['is_write'] ? 'bg-amber-50 border border-amber-200' : 'bg-slate-50 border border-slate-200' }}">
          <span class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full {{ $scope['is_write'] ? 'bg-amber-500' : 'bg-emerald-500' }} text-white text-xs flex items-center justify-center">{{ $scope['is_write'] ? '!' : '✓' }}</span>
          <div>
            <div class="font-medium text-slate-900">{{ $scope['description'] }}</div>
            <div class="text-xs text-slate-500 mt-0.5">{{ $scope['id'] }}</div>
          </div>
        </li>
      @endforeach
    </ul>

    <form method="POST" action="{{ $authorize_url }}" class="space-y-4">
      @csrf
      <input type="hidden" name="state" value="{{ $state }}" />
      <input type="hidden" name="auth_token" value="{{ $auth_token }}" />

      <div class="border-t border-slate-200 pt-4">
        <label class="block text-sm font-medium text-slate-900 mb-2">Daily spending limit</label>
        <p class="text-xs text-slate-500 mb-3">Maximum total per 24 hours across payments, on/offramp sessions and SMS sends. Exchange trades are not yet counted against this cap. Will be enforced when you authorize this app.</p>
        <select name="mcp_daily_limit_minor" class="w-full rounded-lg border-slate-300 focus:ring-emerald-500 focus:border-emerald-500">
          @foreach($spending_options as $opt)
            <option value="{{ $opt ?? '' }}" {{ $opt === $default_limit_minor ? 'selected' : '' }}>
              @if($opt === null) No limit — I trust this app fully
              @else {{ $currency }} {{ number_format($opt / 100, 2) }} / 24h
              @endif
            </option>
          @endforeach
        </select>
      </div>

      <div class="flex gap-3 pt-4">
        <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded-lg">Approve</button>
      </div>
    </form>

    <form method="POST" action="{{ $deny_url }}" class="mt-3">
      @csrf
      @method('DELETE')
      <input type="hidden" name="state" value="{{ $state }}" />
      <input type="hidden" name="auth_token" value="{{ $auth_token }}" />
      <button type="submit" class="w-full text-slate-600 hover:text-slate-900 text-sm py-2">Deny</button>
    </form>
  </div>
</div>
@endsection
