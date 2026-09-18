<div class="mx-auto my-16 max-h-fit w-full max-w-[1200px] max-[1200px]:w-[80%] max-[800px]:w-[90%] max-[720px]:mb-24" x-show="!uiHidden">
    <ul class="mx-auto flex w-full list-none flex-wrap items-center justify-center gap-4 p-0">
        @foreach ($radios as $radio)
            <li class="bg-menu text-app-text hover:bg-menu-hover flex h-auto w-[200px] cursor-pointer flex-row items-center justify-evenly rounded-[10px] p-2 transition-all duration-300 ease-in-out max-[720px]:w-[155px] max-[720px]:text-[.8rem]"
                @click="select({{ $radio->id }})">
                <div class="max-[720px]:flex-[1_0_50px]">
                    <img class="h-[65px] w-[65px] rounded-full object-cover max-[800px]:h-[75px] max-[800px]:w-[75px] max-[720px]:h-[50px] max-[720px]:w-[50px]"
                         src="{{ $radio->image }}" alt="{{ $radio->title }}" loading="lazy">
                </div>
                <div class="max-[720px]:flex max-[720px]:w-full max-[720px]:flex-col max-[720px]:p-1">
                    <h4>{{ $radio->title }}</h4>
                </div>
            </li>
        @endforeach
    </ul>
</div>
