<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga de PDF con DomPDF (barryvdh/laravel-dompdf).
 *
 * Si el paquete aún no está instalado, en vez de romper la página se vuelve atrás
 * con un mensaje que explica el comando que falta.
 */
class PdfExporter
{
    public const FACADE = Pdf::class;

    public static function isAvailable(): bool
    {
        return class_exists(self::FACADE);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function download(string $view, array $data, string $filename, string $orientation = 'portrait'): Response|RedirectResponse
    {
        if (! self::isAvailable()) {
            return back()->with('error', 'Para exportar a PDF falta instalar DomPDF. En la terminal del proyecto ejecuta: composer require barryvdh/laravel-dompdf');
        }

        $facade = self::FACADE;

        return $facade::loadView($view, $data)
            ->setPaper('a4', $orientation)
            ->download($filename);
    }
}
