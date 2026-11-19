<?php

/**
 * Métricas internas del backend para la matriz de calidad y el registro de code smells del E4.
 *
 * Recorre backend/app con el analizador de PHP que ya trae Laravel (nikic/php-parser, vía psy/psysh) y mide, por
 * método: líneas y complejidad ciclomática (1 + cada if, elseif, for, foreach, while, do, case, catch, ?:, ??, &&,
 * ||, and, or y brazo de match que no sea "default"); por clase: líneas y número de métodos.
 *
 * Umbrales de los code smells (los usuales de PHPMD y SonarQube para PHP):
 *   método largo > 60 líneas · método complejo > 10 · lista larga de parámetros > 5 · clase grande > 500 líneas.
 *
 * Uso (desde la raíz del repositorio):
 *   php docs/entregables/E4-gestion-y-calidad/calidad/medir-codigo.php            resumen en texto
 *   php docs/entregables/E4-gestion-y-calidad/calidad/medir-codigo.php --json     todo, para otras herramientas
 */

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

$root = dirname(__DIR__, 4);
require $root.'/backend/vendor/autoload.php';

const LONG_METHOD = 60;
const COMPLEX_METHOD = 10;
const LONG_PARAMETERS = 5;
const LARGE_CLASS = 500;

$parser = (new ParserFactory)->createForHostVersion();
$finder = new NodeFinder;
$names = new NodeTraverser(new NameResolver);
$decisions = [
    Node\Stmt\If_::class, Node\Stmt\ElseIf_::class, Node\Stmt\For_::class, Node\Stmt\Foreach_::class,
    Node\Stmt\While_::class, Node\Stmt\Do_::class, Node\Stmt\Catch_::class, Node\Expr\Ternary::class,
    Node\Expr\BinaryOp\Coalesce::class, Node\Expr\BinaryOp\BooleanAnd::class, Node\Expr\BinaryOp\BooleanOr::class,
    Node\Expr\BinaryOp\LogicalAnd::class, Node\Expr\BinaryOp\LogicalOr::class,
];

$methods = [];
$classes = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/backend/app', FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = substr($file->getPathname(), strlen($root) + 1);
    $ast = $names->traverse($parser->parse(file_get_contents($file->getPathname())) ?? []);

    foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $class) {
        $name = isset($class->namespacedName) ? $class->namespacedName->toString() : '(anónima)';
        $classMethods = $class->getMethods();
        $classes[] = [
            'clase' => $name,
            'archivo' => $path,
            'lineas' => $class->getEndLine() - $class->getStartLine() + 1,
            'metodos' => count($classMethods),
        ];

        foreach ($classMethods as $method) {
            $complexity = 1;
            foreach ($finder->find($method->stmts ?? [], fn (Node $node) => in_array($node::class, $decisions, true)
                || ($node instanceof Node\Stmt\Case_ && $node->cond !== null)
                || ($node instanceof Node\MatchArm && $node->conds !== null)) as $ignored) {
                $complexity++;
            }

            $methods[] = [
                'metodo' => basename(str_replace('\\', '/', $name)).'::'.$method->name->toString(),
                'archivo' => $path,
                'linea' => $method->getStartLine(),
                'lineas' => $method->getEndLine() - $method->getStartLine() + 1,
                'complejidad' => $complexity,
                'parametros' => count($method->params),
            ];
        }
    }
}

$smells = [
    'Método largo (> '.LONG_METHOD.' líneas)' => array_values(array_filter($methods, fn ($m) => $m['lineas'] > LONG_METHOD)),
    'Método complejo (complejidad > '.COMPLEX_METHOD.')' => array_values(array_filter($methods, fn ($m) => $m['complejidad'] > COMPLEX_METHOD)),
    'Lista larga de parámetros (> '.LONG_PARAMETERS.')' => array_values(array_filter($methods, fn ($m) => $m['parametros'] > LONG_PARAMETERS)),
    'Clase grande (> '.LARGE_CLASS.' líneas)' => array_values(array_filter($classes, fn ($c) => $c['lineas'] > LARGE_CLASS)),
];

$complexities = array_column($methods, 'complejidad');
sort($complexities);
$summary = [
    'clases' => count($classes),
    'metodos' => count($methods),
    'complejidad_media' => round(array_sum($complexities) / max(1, count($complexities)), 2),
    'complejidad_p90' => $complexities[(int) floor(0.9 * (count($complexities) - 1))] ?? 0,
    'complejidad_maxima' => max($complexities ?: [0]),
    'metodos_con_complejidad_hasta_5' => round(100 * count(array_filter($complexities, fn ($c) => $c <= 5)) / max(1, count($complexities)), 1),
    'lineas_media_por_metodo' => round(array_sum(array_column($methods, 'lineas')) / max(1, count($methods)), 1),
];

if (in_array('--json', $argv, true)) {
    echo json_encode(['resumen' => $summary, 'smells' => $smells, 'metodos' => $methods, 'clases' => $classes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;

    exit(0);
}

foreach ($summary as $key => $value) {
    echo str_pad($key, 34), $value, PHP_EOL;
}

foreach ($smells as $smell => $items) {
    echo PHP_EOL, $smell, ': ', count($items), PHP_EOL;
    usort($items, fn ($a, $b) => ($b['complejidad'] ?? $b['lineas']) <=> ($a['complejidad'] ?? $a['lineas']));
    foreach (array_slice($items, 0, 12) as $item) {
        echo '  ', $item['metodo'] ?? $item['clase'], '  ', $item['lineas'], ' líneas',
            isset($item['complejidad']) ? '  CC '.$item['complejidad'].'  '.$item['parametros'].' parám.' : '  '.$item['metodos'].' métodos',
            '  ', $item['archivo'], isset($item['linea']) ? ':'.$item['linea'] : '', PHP_EOL;
    }
}
