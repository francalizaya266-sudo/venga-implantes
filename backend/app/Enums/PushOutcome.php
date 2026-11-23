<?php

namespace App\Enums;

/** Cómo le fue a un aviso en el celular. */
enum PushOutcome
{
    /** El servicio de avisos del navegador lo recibió: aparece en la pantalla. */
    case Delivered;

    /** El navegador ya no acepta avisos de la web (se desinstaló, se quitó el permiso): no se vuelve a intentar. */
    case Gone;

    /** No se pudo esta vez (sin conexión, el servicio no respondió): se intenta en la próxima pasada. */
    case Failed;
}
