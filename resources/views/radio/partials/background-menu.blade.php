<div class="flex w-full flex-col">
    <h5 class="flex w-full flex-col text-base font-extrabold max-[720px]:text-2xl">
        Select Background
    </h5>
    <div class="mx-auto my-4 flex w-full flex-row flex-wrap items-center justify-center gap-4 max-[720px]:grid max-[720px]:grid-cols-2 max-[720px]:justify-items-center max-[720px]:gap-y-[1.4rem]">
        @foreach (\App\Http\Controllers\BackgroundController::CATEGORIES as $category)
            <div class="border-btn-border bg-btn text-app-text hover:bg-btn-hover flex h-auto max-w-[150px] min-w-[120px] cursor-pointer flex-col items-center justify-evenly rounded border border-solid p-2 text-[.7rem] font-bold transition-all duration-300 ease-in-out max-[720px]:mx-[.2rem] max-[720px]:text-base"
                 @click="setBackground('{{ $category }}')">
                <div>{{ Str::title($category) }}</div>
            </div>
        @endforeach
    </div>
</div>
