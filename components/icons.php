<?php
function ui_icon(string $name, string $class=''): string {
    $paths = [
        'wallet'=>'<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 9h18M16 13h5M17 16h.01M7 5V3h11v2"/>',
        'users'=>'<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 4a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 4v3"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3"/>',
        'coins'=>'<circle cx="10" cy="10" r="7"/><path d="M10 6v8M12 7H9a1.5 1.5 0 0 0 0 3h2a1.5 1.5 0 0 1 0 3H8M17 7a7 7 0 1 1-10 10"/>',
        'withdraw'=>'<rect x="3" y="3" width="18" height="18" rx="5"/><path d="M12 7v10m-4-4 4 4 4-4"/>',
        'deposit'=>'<rect x="3" y="3" width="18" height="18" rx="5"/><path d="M12 17V7m-4 4 4-4 4 4"/>',
        'play'=>'<path d="m9 5 11 7-11 7Z"/><path d="M4 5v14"/>',
        'shield'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/>',
        'history'=>'<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',
        'copy'=>'<rect x="8" y="8" width="13" height="13" rx="3"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>',
        'link'=>'<path d="m10 14 4-4M8 16l-1 1a4 4 0 0 1-6-6l5-5a4 4 0 0 1 6 0m0 12a4 4 0 0 0 6 0l5-5a4 4 0 0 0-6-6l-1 1"/>',
        'trophy'=>'<path d="M7 3h10v6a5 5 0 0 1-10 0ZM7 5H3v3a4 4 0 0 0 4 4m10-7h4v3a4 4 0 0 1-4 4M12 14v6m-5 1h10"/>',
        'spark'=>'<path d="m12 2 3 7 7 3-7 3-3 7-3-7-7-3 7-3Z"/>',
        'arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'logout'=>'<path d="M9 3H4v18h5m4-14 5 5-5 5m-5-5h13"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
        'sound'=>'<path d="m11 4-6 5H2v6h3l6 5Zm4 4a6 6 0 0 1 0 8m3-11a10 10 0 0 1 0 14"/>',
        'muted'=>'<path d="m11 4-6 5H2v6h3l6 5Zm5 6 5 5m0-5-5 5"/>',
        'help'=>'<circle cx="12" cy="12" r="9"/><path d="M9 9a3 3 0 1 1 5 2c-2 1-2 2-2 3m0 3h.01"/>',
    ];
    return '<svg class="ui-icon '.htmlspecialchars($class,ENT_QUOTES,'UTF-8').'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['spark']).'</svg>';
}
