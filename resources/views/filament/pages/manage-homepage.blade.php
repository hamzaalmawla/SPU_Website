<x-filament-panels::page>
    <x-admin.cms-shell
        area="homepage"
        :state="ucfirst($this->getHomepageState())"
        :state-color="$this->getStateBadgeColor()"
    >
        <section class="spu-task-grid" aria-labelledby="homepage-section-heading">
            <div class="spu-task-grid__intro">
                <p class="spu-workspace__eyebrow">Homepage sections</p>
                <h2 id="homepage-section-heading">Choose one section to edit</h2>
                <p>Only the selected bilingual section is loaded. Other homepage sections remain unchanged when you save.</p>
            </div>

            <div class="spu-task-grid__items">
                @foreach ($this->getWorkspaceSections() as $section)
                    <a
                        href="{{ $section['url'] }}"
                        class="spu-task-card"
                        @if ($section['active']) aria-current="page" @endif
                    >
                        <span class="spu-task-card__mark">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span><strong>{{ $section['label'] }}</strong></span>
                    </a>
                @endforeach
            </div>
        </section>

        <x-filament-panels::form>
            {{ $this->form }}
        </x-filament-panels::form>
    </x-admin.cms-shell>
</x-filament-panels::page>
