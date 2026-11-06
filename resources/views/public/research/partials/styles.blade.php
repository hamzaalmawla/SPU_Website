@once
    @push('styles')
        <style>
            .research-filter-grid-publications { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
            .research-filter-grid-projects { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
            .research-detail-grid { display: grid; gap: 2rem; }
            .research-project-detail-grid { display: grid; grid-template-columns: 1fr; gap: 3rem; }

            @media (min-width: 768px) {
                .research-filter-grid-publications { grid-template-columns: 220px 220px 220px 1fr; }
                .research-filter-grid-projects { grid-template-columns: 180px 180px 180px 1fr; }
            }

            @media (min-width: 1024px) {
                .research-detail-grid { grid-template-columns: minmax(0, 7fr) minmax(280px, 3fr); align-items: start; }
                .research-project-detail-grid { grid-template-columns: 1fr 320px; }
                .research-centers-intro-grid { display: grid; grid-template-columns: 1fr 1px 0.82fr; align-items: center; }
            }
        </style>
    @endpush
@endonce
