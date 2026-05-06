# Lineforge Icon System

Lineforge icons are custom inline SVGs built for dark sports intelligence UI.

## Drawing Rules

- 24 by 24 viewBox.
- Thin-to-medium 1.65 stroke.
- Rounded caps and joins.
- Outline-first geometry with minimal filled dots.
- Electric blue accent strokes use `.lf-accent`.
- Filled status nodes use `.lf-dot`.
- Icons inherit current text color and are styled by `premium.css`.
- Avoid casino, chip, dice, slot, or cartoon imagery.

## Product Icons

- `dashboard`
- `live-games`
- `ai-signals`
- `odds`
- `market-analysis`
- `line-movement`
- `confidence-score`
- `edge-rating`
- `steam-move`
- `sharp-money`
- `injury-risk`
- `volatility`
- `analytics`
- `watchlists`
- `alerts`
- `bankroll-tracking`
- `matchup-analysis`
- `historical-trends`
- `sportsbooks`
- `settings`
- `user-profile`

## Micro Glyphs

- `probability-bars`
- `live-feed`
- `trend-up`
- `trend-down`
- `confidence-ring`
- `status-indicator`
- `signal-pulse`
- `risk-tier`

## Usage

PHP-rendered surfaces call:

```php
lineforge_icon('ai-signals');
```

Sports dashboard compatibility calls:

```php
sports_icon('ai-signals');
```

JS-refreshed dashboard surfaces call:

```js
lineforgeIconLabel("Readiness", "confidence-ring")
```
