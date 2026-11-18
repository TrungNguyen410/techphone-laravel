{{-- Robot trợ lý AI. Chớp mắt, nhấp nháy ăng-ten, lắc lư khi đang suy nghĩ (tắt khi người dùng giảm chuyển động). --}}
<svg class="robot-mascot {{ $class ?? '' }}" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
    <path d="M32 14V8" fill="none" stroke-width="2.4" stroke-linecap="round" style="stroke: var(--color-accent)"/>
    <circle class="robot-antenna" cx="32" cy="6" r="3.4" style="fill: var(--color-cyan-bright)"/>
    <rect x="4.5" y="27" width="6.5" height="13" rx="3.25" style="fill: var(--color-accent)"/>
    <rect x="53" y="27" width="6.5" height="13" rx="3.25" style="fill: var(--color-accent)"/>
    <rect x="24" y="49" width="16" height="7" rx="3" style="fill: var(--color-accent-strong)"/>
    <rect x="9" y="13" width="46" height="38" rx="14" style="fill: var(--color-accent)"/>
    <path d="M15 22C16.5 17.5 20 15.5 25 15.5" fill="none" stroke-width="2" stroke-linecap="round" opacity="0.6" style="stroke: var(--color-static-white)"/>
    <rect x="15" y="21" width="34" height="23" rx="10" style="fill: var(--color-graphite-deep)"/>
    <g class="robot-eyes" style="fill: var(--color-cyan-bright)">
        <rect class="robot-eye" x="22" y="27" width="6" height="8.5" rx="3"/>
        <rect class="robot-eye" x="36" y="27" width="6" height="8.5" rx="3"/>
    </g>
    <path d="M28.5 39Q32 41.6 35.5 39" fill="none" stroke-width="1.8" stroke-linecap="round" style="stroke: var(--color-cyan-soft)"/>
</svg>
