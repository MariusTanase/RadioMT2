<div class="text-app-text fixed top-0 right-0 z-100 flex h-auto w-fit flex-col items-center justify-center p-4 max-[720px]:top-auto max-[720px]:bottom-0 max-[720px]:left-0 max-[720px]:mx-auto max-[720px]:h-20 max-[720px]:w-full"
     x-data="radioSettings"
     x-init="setBackground('mountain')"
     x-bind:class="menuOpen ? 'max-[720px]:bg-transparent' : 'max-[720px]:bg-menu'">
    <button class="text-app-text cursor-pointer border-none bg-none text-5xl outline-none transition-all duration-300 ease-in-out"
            x-show="!menuOpen" @click="open()" aria-label="Open settings">
        <x-icon.gear class="animate-gear" />
    </button>

    <div class="bg-menu absolute top-0 right-0 z-100 flex h-auto w-80 translate-x-full flex-col items-center rounded-l-lg rounded-br-lg p-4 transition-all duration-300 ease-in-out max-[720px]:fixed max-[720px]:top-0 max-[720px]:left-0 max-[720px]:h-full max-[720px]:w-full max-[720px]:justify-between max-[720px]:rounded-none max-[720px]:pt-24"
         x-bind:class="menuOpen ? '!translate-x-0' : ''">
        <div class="pt-12">
            @include('radio.partials.theme-menu')
            @include('radio.partials.background-menu')

            <h5 class="flex w-full flex-col text-base font-extrabold max-[720px]:text-2xl">Extra</h5>

            <button class="border-btn-border bg-btn text-app-text hover:bg-btn-hover mx-auto my-4 flex h-auto w-fit cursor-pointer flex-col items-center justify-evenly rounded border border-solid p-2 text-base font-bold transition-all duration-300 ease-in-out max-[720px]:mb-20 max-[720px]:rounded-xl max-[720px]:p-4 max-[720px]:text-[1.3rem]"
                    @click="toggleUi()"
                    x-text="uiHidden ? 'Show UI' : 'Hide UI'"></button>
        </div>

        <button class="text-app-text absolute top-[3%] right-[6%] h-auto w-fit cursor-pointer rounded-full border-none bg-transparent text-5xl font-bold outline-none transition-all duration-300 ease-in-out"
                @click="close()" aria-label="Close settings">X</button>
    </div>
</div>
