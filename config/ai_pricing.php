<?php

/*
|--------------------------------------------------------------------------
| Precios de IA (estimación de costos)
|--------------------------------------------------------------------------
|
| Precios usados para estimar el costo de las operaciones de IA que se
| muestran en la pestaña "Costos" de cada agente.
|
| - Modelos de texto/visión: 'in' y 'out' en USD por 1M de tokens.
| - Modelos de audio (transcripción): 'per_minute' en USD por minuto.
|
| Estos valores sobrescriben los precios por defecto de AiCostCalculator.
|
*/

return [
    'models' => [
        'gpt-4.1-mini' => ['in' => 0.40, 'out' => 1.60],
        'gpt-4.1-nano' => ['in' => 0.10, 'out' => 0.40],
        'gpt-4.1' => ['in' => 2.00, 'out' => 8.00],
        'gpt-4o-mini' => ['in' => 0.15, 'out' => 0.60],
        'gpt-4o' => ['in' => 2.50, 'out' => 10.00],
        'gpt-4o-mini-transcribe' => ['in' => 1.25, 'out' => 5.00],
        'whisper-1' => ['per_minute' => 0.006],
    ],
];
