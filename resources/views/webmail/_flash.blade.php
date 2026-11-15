@if(session('status'))
    <p class="rounded-2xl bg-emerald-50 p-4 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</p>
@endif
@if($errors->has('webmail') || ! empty($error))
    <p class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-800" role="alert">{{ $errors->first('webmail') ?: $error }}</p>
@endif
@if($errors->has('send'))
    <p class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-800" role="alert">{{ $errors->first('send') }}</p>
@endif
