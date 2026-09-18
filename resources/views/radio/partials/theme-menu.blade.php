<div class="flex w-full flex-col">
    <h5 class="flex w-full flex-col text-base font-extrabold max-[720px]:text-2xl">
        <p>Select Theme</p>
    </h5>
    <div class="mx-auto my-4 flex w-full flex-row flex-wrap items-center justify-center gap-4">
        @foreach (['Light', 'Dark', 'Crimson', 'Blue'] as $name)
            <div class="border-btn-border bg-btn text-app-text hover:bg-btn-hover flex h-auto w-fit cursor-pointer flex-col items-center justify-evenly rounded border border-solid p-2 text-[.7rem] font-bold transition-all duration-300 ease-in-out max-[720px]:mx-[.2rem] max-[720px]:text-base"
                 @click="setTheme('{{ Str::lower($name) }}')">
                <span>{{ $name }}</span>
            </div>
        @endforeach
    </div>
</div>
