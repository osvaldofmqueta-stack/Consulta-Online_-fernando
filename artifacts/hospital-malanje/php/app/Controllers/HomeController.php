<?php
declare(strict_types=1);

final class HomeController
{
    public function index(): never
    {
        View::render('home/index');
    }
}