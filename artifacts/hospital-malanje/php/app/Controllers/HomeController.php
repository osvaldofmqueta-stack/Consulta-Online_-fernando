<?php
/** Controlador da página pública inicial. */
declare(strict_types=1);

final class HomeController
{
    /** Mostra a apresentação pública e os pontos de entrada da aplicação. */
    public function index(): never
    {
        View::render('home/index');
    }
}