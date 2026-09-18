<div class="bg-menu text-app-text relative mx-auto mt-8 w-[350px] rounded-t-2xl rounded-b-[2rem] p-6 shadow-[0_18px_28px_var(--color-menu)]"
     x-show="!uiHidden">
    <div class="text-center">
        <img class="border-app-text mx-auto mb-4 block h-[150px] w-[150px] rounded-full border border-solid object-cover"
             x-bind:src="current?.image"
             x-bind:alt="current ? `Image of ${current.title}` : ''">
        <h2 class="mb-1 font-bold" x-text="current?.title"></h2>
        <h3 class="mt-0 font-light" x-text="current ? `Genre: ${current.artist}` : ''"></h3>
    </div>

    <div class="mx-auto my-2 flex w-3/5 flex-col items-center justify-between gap-2.5">
        <div class="mx-auto my-2 flex w-full items-center justify-evenly gap-2.5">
            <button class="hover:text-icon-hover border-none bg-transparent text-[2em] font-light transition-all duration-200 hover:cursor-pointer"
                    @click="previous()" aria-label="Previous station">
                <x-icon.backward />
            </button>

            <button class="hover:text-icon-hover border-none bg-transparent text-[2em] font-light transition-all duration-200 hover:cursor-pointer"
                    @click="toggle()" x-bind:aria-label="isPlaying ? 'Pause' : 'Play'">
                <template x-if="isPlaying"><x-icon.pause /></template>
                <template x-if="!isPlaying"><x-icon.play /></template>
            </button>

            <button class="hover:text-icon-hover border-none bg-transparent text-[2em] font-light transition-all duration-200 hover:cursor-pointer"
                    @click="next()" aria-label="Next station">
                <x-icon.forward />
            </button>
        </div>

        <div class="mx-auto my-2 flex w-full items-center justify-between gap-4">
            <button class="hover:text-icon-hover border-none bg-transparent text-[2em] font-light transition-all duration-200 hover:cursor-pointer"
                    @click="shuffle()" aria-label="Random station">
                <x-icon.shuffle />
            </button>

            <input type="range" min="0" max="1" step="0.01" class="volume-slider"
                   aria-label="Volume"
                   x-model.number="volume"
                   @input="setVolume($event.target.value)">
        </div>
    </div>

    <audio x-ref="audio"
           x-bind:src="current?.url"
           @play="isPlaying = true"
           @pause="isPlaying = false"
           x-on:error="isPlaying = false"></audio>
</div>
