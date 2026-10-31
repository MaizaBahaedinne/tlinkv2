@if(session('status'))
    <p class="rounded-2xl bg-emerald-50 p-4 text-sm font-medium text-emerald-800" role="status">{{ session('status') }}</p>
@endif
@if($errors->any())
    <div class="rounded-2xl bg-rose-50 p-4 text-sm text-rose-800" role="alert">
        <ul class="list-disc space-y-1 pl-5">
            @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    </div>
@endif
