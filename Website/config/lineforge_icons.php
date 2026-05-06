<?php

function lineforge_icon(string $name, string $className = ''): string
{
    static $icons = [
        'dashboard' => '<rect x="4" y="4" width="7" height="7" rx="2"></rect><rect x="13" y="4" width="7" height="5" rx="2"></rect><rect x="13" y="11" width="7" height="9" rx="2"></rect><path class="lf-accent" d="M5.8 16.5h2.1l1.6-3 1.8 5 1.5-2h3.4"></path>',
        'live-games' => '<rect x="5" y="6" width="14" height="12" rx="3"></rect><circle class="lf-dot" cx="12" cy="12" r="2"></circle><path class="lf-accent" d="M3.5 12a8.5 8.5 0 0 1 2-5.4"></path><path class="lf-accent" d="M20.5 12a8.5 8.5 0 0 0-2-5.4"></path><path d="M8.2 17.8h7.6"></path>',
        'ai-signals' => '<circle cx="7" cy="8" r="2"></circle><circle cx="17" cy="8" r="2"></circle><circle cx="12" cy="16" r="2.4"></circle><path d="M8.7 9.4l2.2 4.1"></path><path d="M15.3 9.4l-2.2 4.1"></path><path class="lf-accent" d="M5 16h2.4l1.4-3 2.1 5.8 2.1-5.1 1.3 2.3H19"></path>',
        'odds' => '<rect x="5" y="4" width="14" height="16" rx="3"></rect><path d="M8 8h8"></path><path d="M8 12h3.5"></path><path d="M13.5 12H16"></path><path class="lf-accent" d="M8 16h2.4l1.6-7 1.7 7H16"></path>',
        'market-analysis' => '<rect x="4" y="5" width="16" height="14" rx="3"></rect><path d="M8 15V9"></path><path d="M12 15V7"></path><path d="M16 15v-4"></path><path class="lf-accent" d="M7 16h10"></path><circle class="lf-dot" cx="12" cy="7" r="1"></circle>',
        'line-movement' => '<path d="M4 18h16"></path><path class="lf-accent" d="M5 15l4-4 3 2 5-6 2 2"></path><circle class="lf-dot" cx="9" cy="11" r="1.2"></circle><circle class="lf-dot" cx="17" cy="7" r="1.2"></circle>',
        'confidence-score' => '<path d="M5 14a7 7 0 1 1 14 0"></path><path d="M7 18h10"></path><path class="lf-accent" d="M12 14l4-5"></path><path d="M8 14h.1"></path><path d="M16 14h.1"></path>',
        'edge-rating' => '<path d="M12 4l7 6-7 10-7-10z"></path><path d="M8 10h8"></path><path class="lf-accent" d="M9.2 14h2l1-2.6 1.4 4 1.2-1.4h2"></path>',
        'steam-move' => '<path class="lf-accent" d="M4 16c4.7-5.6 8.7-6.5 15-4"></path><path d="M5 8h7"></path><path d="M3 12h8"></path><path d="M6 18h10"></path><path d="M16 8l3 3-3 3"></path>',
        'sharp-money' => '<path d="M5 8h9.5a4.5 4.5 0 0 1 4.5 4.5v0a4.5 4.5 0 0 1-4.5 4.5H5"></path><path d="M5 5v14"></path><path class="lf-accent" d="M8 12h8"></path><path class="lf-accent" d="M13 9l3 3-3 3"></path>',
        'injury-risk' => '<path d="M12 4l7 3v5.2c0 4.2-2.7 6.8-7 7.8-4.3-1-7-3.6-7-7.8V7z"></path><path class="lf-accent" d="M12 8v8"></path><path class="lf-accent" d="M8 12h8"></path>',
        'volatility' => '<path d="M4 17h16"></path><path class="lf-accent" d="M5 13l2.8-5 3.2 9 2.5-6 2 3 3.5-7"></path><path d="M6 20h12"></path>',
        'analytics' => '<rect x="4" y="5" width="16" height="14" rx="3"></rect><path d="M8 15v-3"></path><path d="M12 15V8"></path><path d="M16 15v-5"></path><path class="lf-accent" d="M7 10.5c2.3 1.2 4.2 1.2 6 0 1.2-.8 2.2-1.2 4-.7"></path>',
        'watchlists' => '<path d="M7 4h10a2 2 0 0 1 2 2v14l-7-3.5L5 20V6a2 2 0 0 1 2-2z"></path><path class="lf-accent" d="M9 9h6"></path><path class="lf-accent" d="M9 13h4"></path>',
        'alerts' => '<path d="M6 10.8a6 6 0 0 1 12 0c0 4.8 1.8 5 1.8 6.7H4.2c0-1.7 1.8-1.9 1.8-6.7z"></path><path d="M9.8 20h4.4"></path><path class="lf-accent" d="M12 4V2.8"></path>',
        'bankroll-tracking' => '<rect x="4" y="6" width="16" height="12" rx="3"></rect><path d="M7 10h4"></path><path d="M15 10h2"></path><path class="lf-accent" d="M7 15l2.4-2.4 2.2 1.8 4.2-4.4"></path>',
        'matchup-analysis' => '<rect x="4" y="6" width="6.5" height="12" rx="2"></rect><rect x="13.5" y="6" width="6.5" height="12" rx="2"></rect><path class="lf-accent" d="M10.5 12h3"></path><path d="M7.3 10h.1"></path><path d="M16.7 14h.1"></path>',
        'historical-trends' => '<path d="M12 4a8 8 0 1 0 8 8"></path><path d="M12 7v5l3 2"></path><path class="lf-accent" d="M14 6h5v5"></path><path class="lf-accent" d="M19 6l-6 6"></path>',
        'sportsbooks' => '<rect x="5" y="5" width="14" height="14" rx="3"></rect><path d="M8 9h8"></path><path d="M8 13h5"></path><path class="lf-accent" d="M8 17h8"></path><path d="M16 13h.1"></path>',
        'settings' => '<path d="M5 7h14"></path><path d="M5 12h14"></path><path d="M5 17h14"></path><circle class="lf-dot" cx="9" cy="7" r="1.8"></circle><circle class="lf-dot" cx="15" cy="12" r="1.8"></circle><circle class="lf-dot" cx="11.5" cy="17" r="1.8"></circle>',
        'user-profile' => '<circle cx="12" cy="8" r="3"></circle><path d="M5.5 19a6.5 6.5 0 0 1 13 0"></path><path class="lf-accent" d="M18 5.5l2-2"></path>',
        'probability-bars' => '<path d="M5 18h14"></path><path d="M7 18v-5"></path><path d="M12 18V8"></path><path class="lf-accent" d="M17 18V5"></path>',
        'live-feed' => '<circle class="lf-dot" cx="12" cy="12" r="2"></circle><path d="M7.5 8.5a5 5 0 0 0 0 7"></path><path d="M16.5 8.5a5 5 0 0 1 0 7"></path><path class="lf-accent" d="M4.8 6a9 9 0 0 0 0 12"></path><path class="lf-accent" d="M19.2 6a9 9 0 0 1 0 12"></path>',
        'trend-up' => '<path d="M5 16h14"></path><path class="lf-accent" d="M6 14l4-4 3 2 5-6"></path><path class="lf-accent" d="M15 6h3v3"></path>',
        'trend-down' => '<path d="M5 8h14"></path><path class="lf-accent" d="M6 10l4 4 3-2 5 6"></path><path class="lf-accent" d="M15 18h3v-3"></path>',
        'confidence-ring' => '<circle cx="12" cy="12" r="7"></circle><path class="lf-accent" d="M12 5a7 7 0 0 1 6.5 9.6"></path><path d="M9.5 12l1.8 1.8 3.4-4"></path>',
        'status-indicator' => '<rect x="5" y="7" width="14" height="10" rx="5"></rect><circle class="lf-dot" cx="10" cy="12" r="2"></circle><path class="lf-accent" d="M13.5 12h2.5"></path>',
        'signal-pulse' => '<path d="M4 12h3l1.5-4 3 8 2-5 1.4 3H20"></path><path class="lf-accent" d="M4 17h16"></path>',
        'risk-tier' => '<path d="M12 4l7 4v4.4c0 4-2.7 6.3-7 7.6-4.3-1.3-7-3.6-7-7.6V8z"></path><path class="lf-accent" d="M9 14h6"></path><path class="lf-accent" d="M10 10h4"></path>',
        'calendar' => '<rect x="4" y="6" width="16" height="13" rx="3"></rect><path d="M8 4v4"></path><path d="M16 4v4"></path><path d="M4 10h16"></path><path class="lf-accent" d="M8 14h3"></path><path class="lf-accent" d="M13 14h3"></path>',
        'plus' => '<rect x="5" y="5" width="14" height="14" rx="4"></rect><path class="lf-accent" d="M12 8v8"></path><path class="lf-accent" d="M8 12h8"></path>',
        'basketball' => '<circle cx="12" cy="12" r="8"></circle><path d="M4 12h16"></path><path class="lf-accent" d="M12 4c-2 3.1-2 12.9 0 16"></path><path class="lf-accent" d="M12 4c2 3.1 2 12.9 0 16"></path>',
        'football' => '<path d="M4 12c2.2-5.2 13.8-5.2 16 0-2.2 5.2-13.8 5.2-16 0z"></path><path class="lf-accent" d="M9 12h6"></path><path d="M11 10v4"></path><path d="M13 10v4"></path>',
        'baseball' => '<circle cx="12" cy="12" r="8"></circle><path class="lf-accent" d="M8 6c2.2 2.4 2.2 9.6 0 12"></path><path class="lf-accent" d="M16 6c-2.2 2.4-2.2 9.6 0 12"></path>',
        'hockey' => '<path d="M7 5v8l4 4"></path><path d="M17 5v8l-4 4"></path><path class="lf-accent" d="M8 19h8"></path>',
        'soccer' => '<circle cx="12" cy="12" r="8"></circle><path class="lf-accent" d="M12 8l4 3-1.6 5h-4.8L8 11z"></path>',
        'combat' => '<path d="M8 5h5.2L17 9v10H9v-6H6V8z"></path><path class="lf-accent" d="M13 5v6"></path><path d="M9 13h8"></path>',
        'tennis' => '<circle cx="10" cy="10" r="6"></circle><path class="lf-accent" d="M14.5 14.5L20 20"></path><path d="M6 10h8"></path><path d="M10 6v8"></path>',
        'monitor' => '<rect x="4" y="5" width="16" height="11" rx="3"></rect><path d="M9 20h6"></path><path d="M12 16v4"></path><path class="lf-accent" d="M8 10h2.2l1.4 3 1.4-5 1.6 2H17"></path>',
    ];

    static $aliases = [
        'grid' => 'dashboard',
        'live' => 'live-games',
        'brain' => 'ai-signals',
        'scan' => 'market-analysis',
        'prop' => 'odds',
        'arbitrage' => 'edge-rating',
        'wallet' => 'bankroll-tracking',
        'report' => 'analytics',
        'bell' => 'alerts',
        'ticket' => 'sportsbooks',
    ];

    $key = strtolower(trim($name));
    $key = $aliases[$key] ?? $key;
    $markup = $icons[$key] ?? $icons['dashboard'];
    $safeKey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
    $safeClass = trim('lineforge-icon betedge-svg-icon ' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8'));

    return '<svg class="' . $safeClass . '" data-lineforge-icon="' . $safeKey . '" aria-hidden="true" focusable="false" viewBox="0 0 24 24">' . $markup . '</svg>';
}

function lineforge_icon_names(): array
{
    return [
        'dashboard',
        'live-games',
        'ai-signals',
        'odds',
        'market-analysis',
        'line-movement',
        'confidence-score',
        'edge-rating',
        'steam-move',
        'sharp-money',
        'injury-risk',
        'volatility',
        'analytics',
        'watchlists',
        'alerts',
        'bankroll-tracking',
        'matchup-analysis',
        'historical-trends',
        'sportsbooks',
        'settings',
        'user-profile',
        'probability-bars',
        'live-feed',
        'trend-up',
        'trend-down',
        'confidence-ring',
        'status-indicator',
        'signal-pulse',
        'risk-tier',
    ];
}
