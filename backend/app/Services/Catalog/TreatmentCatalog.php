<?php

namespace App\Services\Catalog;

use App\Enums\TreatmentKind;
use App\Models\Doctor;
use App\Models\Treatment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Los tratamientos que el sistema trae escritos, para que el consultorio no empiece de cero: cada uno con su
 * duración, sus sesiones, lo que se le pide al paciente antes de venir, los cuidados para después y, los de
 * siempre, el texto de su página en la web. El precio no viene: lo pone cada consultorio.
 *
 * Nunca pisa lo que el consultorio escribió: agrega lo que falta y llena solo lo que está vacío.
 *
 * @phpstan-type Definition array{name: string, minutes: int, sessions: string, summary: string, evaluation?: bool, bookable?: bool, kind?: TreatmentKind, checkup_months?: int, before?: string, aftercare?: string, description?: string}
 */
final class TreatmentCatalog
{
    /**
     * Con los que nace el sistema: los tipos de cita de casi cualquier consultorio. Salen en la página web.
     *
     * @var list<Definition>
     */
    public const BASE = [
        [
            'name' => 'Evaluación o primera consulta',
            'minutes' => 30,
            'sessions' => '1',
            'evaluation' => true,
            'bookable' => true,
            'summary' => 'Revisión completa, diagnóstico y presupuesto. Si no sabes qué necesitas, empieza aquí.',
            'before' => 'Llega 10 minutos antes y trae tu DNI. Si tienes radiografías o exámenes anteriores, tráelos también.',
            'description' => <<<'MD'
                En la primera consulta el doctor revisa tus dientes, tus encías y tu mordida, y te explica con palabras simples qué encontró. Sales sabiendo qué necesitas, en qué orden y cuánto cuesta.

                ## Cómo es la consulta

                1. Conversamos sobre lo que te molesta y sobre tu salud en general.
                2. El doctor te examina y anota lo que encuentra.
                3. Si hace falta, te indica una radiografía.
                4. Te explica las opciones y te entrega tu presupuesto.

                No te comprometes a nada: decides con calma si empiezas el tratamiento y cuándo.
                MD,
        ],
        [
            'name' => 'Control de ortodoncia',
            'minutes' => 30,
            'sessions' => 'Una vez al mes',
            'summary' => 'Ajuste y seguimiento de tus brackets o alineadores.',
            'before' => 'Ven con los dientes bien cepillados y trae tus ligas o retenedores si los usas. Si se despegó un bracket, avísanos antes para darte más tiempo.',
            'description' => <<<'MD'
                Es la cita mensual de quienes llevan brackets o alineadores. El doctor revisa cómo se van moviendo tus dientes y hace el ajuste que toca ese mes.

                ## Cómo es el control

                1. Revisamos tu higiene y que no haya brackets sueltos.
                2. Cambiamos las ligas y ajustamos o cambiamos el arco.
                3. Te contamos cómo vas y qué sigue.

                Es normal sentir presión dos o tres días después. Faltar a los controles alarga el tratamiento: procura venir cada mes.
                MD,
        ],
        [
            'name' => 'Limpieza dental',
            'minutes' => 45,
            'sessions' => '1',
            'bookable' => true,
            'summary' => 'Retiro de sarro y placa, y pulido. Lo ideal es hacerla cada seis meses.',
            'before' => 'Ven con los dientes cepillados.',
            'aftercare' => "- Es normal sentir sensibilidad uno o dos días.\n- Cepíllate tres veces al día y usa hilo dental.\n- Te esperamos en seis meses para tu próxima limpieza.",
            'description' => <<<'MD'
                La limpieza retira el sarro y la placa que el cepillo no alcanza, sobre todo entre los dientes y bajo la encía. Así se previenen las caries, el sangrado de encías y el mal aliento.

                ## Cómo es la sesión

                1. Revisamos tus dientes y encías.
                2. Retiramos el sarro con ultrasonido y pulimos.
                3. Te damos indicaciones de cepillado según lo que vimos.

                No suele doler. Si tienes las encías sensibles, avísanos y vamos más despacio.
                MD,
        ],
        [
            'name' => 'Curación con resina',
            'minutes' => 60,
            'sessions' => '1',
            'summary' => 'Repara caries o dientes rotos con resina del color de tu diente.',
            'before' => 'Ven con los dientes cepillados y habiendo comido, porque puedes salir con la boca adormecida.',
            'aftercare' => "- No comas hasta que pase la anestesia, para no morderte el labio o la lengua.\n- Es normal sentir algo de sensibilidad al frío unos días.\n- Si al morder sientes la curación alta, avísanos para ajustarla.",
            'description' => <<<'MD'
                Cuando una caries daña el diente, retiramos la parte dañada y lo reconstruimos con resina del mismo color. El diente recupera su forma y vuelve a servir para masticar.

                ## Cómo es la sesión

                1. Ponemos anestesia local si hace falta, para que no sientas dolor.
                2. Retiramos la caries y limpiamos la zona.
                3. Colocamos la resina por capas y la endurecemos con luz.
                4. Ajustamos la mordida y pulimos.

                Mientras más pequeña es la caries, más sencilla es la curación: por eso conviene tratarla apenas se detecta.
                MD,
        ],
        [
            'name' => 'Extracción simple',
            'minutes' => 45,
            'sessions' => '1',
            'summary' => 'Retiro de una pieza dañada, con anestesia local.',
            'before' => 'Ven habiendo comido algo ligero y con los dientes cepillados. Si tomas medicinas todos los días, avísanos antes.',
            'aftercare' => "- Muerde la gasa 30 minutos y hoy no escupas ni te enjuagues.\n- Ponte hielo por fuera, 10 minutos sí y 10 no, durante las primeras horas.\n- Hoy come blando y frío o tibio, y mastica por el otro lado.\n- No fumes ni tomes con cañita por 3 días.\n- Toma solo lo que te recetó el doctor.\n- Desde mañana cepíllate con cuidado, sin tocar la herida.",
            'description' => <<<'MD'
                Se retira un diente que ya no se puede salvar: por una caries muy avanzada, una fractura o una infección. Se hace con anestesia local, así que sientes presión pero no dolor.

                ## Cómo es la sesión

                1. Revisamos tu salud y las medicinas que tomas.
                2. Ponemos anestesia local y esperamos a que haga efecto.
                3. Retiramos la pieza con cuidado.
                4. Te explicamos los cuidados para los días siguientes.

                Antes de extraer, el doctor te dice si el diente se puede salvar y cómo reponerlo después.
                MD,
        ],
        [
            'name' => 'Blanqueamiento',
            'minutes' => 90,
            'sessions' => '1 – 2',
            'summary' => 'Aclara el color de tus dientes en el consultorio, con control del dentista.',
            'before' => 'Ven con los dientes cepillados y evita café, té y gaseosas oscuras desde hoy.',
            'aftercare' => "- Por 48 horas evita café, té, vino, gaseosas oscuras, salsas con color y fumar.\n- Es normal sentir sensibilidad uno o dos días: evita lo muy frío y lo muy caliente.\n- Cepíllate con normalidad.",
            'description' => <<<'MD'
                Aclara el color de tus dientes con un gel que el dentista aplica en el consultorio. No desgasta el diente: actúa sobre las manchas que dejan el café, el té, el tabaco y los años.

                ## Cómo es la sesión

                1. Anotamos el color con el que empiezas.
                2. Protegemos tus encías.
                3. Aplicamos el gel blanqueador en varios ciclos.
                4. Comparamos el color final con el inicial.

                Las curaciones, coronas y carillas no cambian de color con el blanqueamiento. Antes de empezar, el doctor revisa que no tengas caries ni encías inflamadas.
                MD,
        ],
        [
            'name' => 'Endodoncia',
            'minutes' => 90,
            'sessions' => '1 – 3',
            'summary' => 'Tratamiento de conducto para salvar un diente con el nervio dañado.',
            'before' => 'Ven habiendo comido y con los dientes cepillados. Si tienes radiografías de esa pieza, tráelas.',
            'aftercare' => "- No mastiques con ese lado hasta que pase la anestesia.\n- Evita lo duro en esa pieza hasta su restauración final.\n- Es normal sentir sensibilidad al morder unos días.\n- Toma solo lo que te recetó el doctor.",
            'description' => <<<'MD'
                Cuando una caries o un golpe llegan al nervio, el diente duele o se infecta. La endodoncia, o tratamiento de conducto, retira el nervio dañado, limpia el interior del diente y lo sella. Así el diente se salva en vez de extraerse.

                ## Cómo es el tratamiento

                1. Tomamos una radiografía para ver las raíces.
                2. Ponemos anestesia local para que no sientas dolor.
                3. Limpiamos y desinfectamos los conductos.
                4. Los sellamos y dejamos el diente listo para su restauración.

                Puede tomar más de una sesión, según el diente. Después hay que reconstruirlo con una resina o una corona, porque queda más frágil.
                MD,
        ],
        [
            'name' => 'Colocación de brackets',
            'minutes' => 120,
            'sessions' => '1',
            'summary' => 'Inicio de tu ortodoncia para alinear los dientes y la mordida.',
            'before' => 'Ven con los dientes bien cepillados y habiendo comido: es una cita larga.',
            'aftercare' => "- Es normal sentir presión y molestia al morder los primeros días.\n- Esta semana come blando, y evita siempre lo duro y lo pegajoso.\n- Si un bracket o el alambre te roza, cúbrelo con cera de ortodoncia.\n- Cepíllate después de cada comida.\n- Si se despega un bracket, escríbenos.",
            'description' => <<<'MD'
                Es el inicio de tu ortodoncia: pegamos los brackets a tus dientes y colocamos el primer arco, que empieza a moverlos poco a poco hacia su lugar.

                ## Cómo es la sesión

                1. Limpiamos y preparamos tus dientes.
                2. Pegamos cada bracket en su posición.
                3. Colocamos el arco y las ligas.
                4. Te enseñamos a cepillarte y qué alimentos evitar.

                Antes de este día necesitas una evaluación de ortodoncia, con tus radiografías y el plan de tu tratamiento. Después vienes a control una vez al mes.
                MD,
        ],
        [
            'name' => 'Implante',
            'minutes' => 120,
            'sessions' => 'Varias sesiones',
            'summary' => 'Reemplaza un diente perdido con una raíz de titanio y una corona.',
            'before' => 'Ven habiendo comido algo ligero y con los dientes cepillados. Si tomas medicinas todos los días, avísanos antes.',
            'aftercare' => "- Ponte hielo por fuera, 10 minutos sí y 10 no, durante las primeras horas.\n- Hoy no escupas ni te enjuagues, y no fumes por una semana.\n- Come blando y frío o tibio, y mastica por el otro lado.\n- Toma solo lo que te recetó el doctor.\n- Desde mañana cepíllate con cuidado, sin tocar la zona.",
            'description' => <<<'MD'
                Un implante reemplaza la raíz de un diente perdido con un tornillo de titanio que se une al hueso. Sobre él va una corona hecha a la medida, que se ve y funciona como un diente natural, sin tocar los dientes vecinos.

                ## Cómo es el tratamiento

                1. Evaluación con radiografía o tomografía, para medir tu hueso y planificar el implante.
                2. Colocación del implante, con anestesia local.
                3. Unos meses de espera mientras el hueso se une al implante.
                4. Toma de medidas y colocación de la corona.

                No todos los casos son iguales: en tu evaluación el doctor te dice si necesitas algo antes, como un injerto de hueso, y cuánto tiempo tomará el tuyo.
                MD,
        ],
    ];

    /**
     * Los que muchos consultorios también ofrecen. No vienen puestos: quien administra marca los suyos en
     * Tratamientos › "Agregar tratamientos sugeridos". Quedan solo para el consultorio hasta que se les pone precio
     * y se elige si salen en la web.
     *
     * @var list<Definition>
     */
    public const MORE = [
        [
            'name' => 'Urgencia dental',
            'minutes' => 30,
            'sessions' => '1',
            'summary' => 'Atención el mismo día si tienes dolor fuerte, hinchazón, un golpe o un diente roto.',
        ],
        [
            'name' => 'Radiografía periapical',
            'minutes' => 15,
            'sessions' => '1',
            'summary' => 'Radiografía pequeña de uno o dos dientes, para ver la raíz y el hueso.',
            'before' => 'Si estás embarazada o crees que puedes estarlo, avísanos antes.',
        ],
        [
            'name' => 'Extracción de muela del juicio',
            'minutes' => 60,
            'sessions' => '1',
            'summary' => 'Retiro de una muela del juicio que duele, no tiene espacio o daña al diente de al lado.',
            'before' => 'Ven habiendo comido algo ligero y con los dientes cepillados. Trae tu radiografía si la tienes, y avísanos antes si tomas medicinas todos los días.',
            'aftercare' => "- Muerde la gasa 30 minutos y hoy no escupas ni te enjuagues.\n- Ponte hielo por fuera, 10 minutos sí y 10 no, durante las primeras horas.\n- Es normal que la mejilla se hinche dos o tres días.\n- Come blando y frío o tibio, y mastica por el otro lado.\n- No fumes ni tomes con cañita por 3 días.\n- Toma solo lo que te recetó el doctor.\n- Desde mañana cepíllate con cuidado, sin tocar la herida.",
        ],
        [
            'name' => 'Limpieza profunda (curetaje)',
            'minutes' => 60,
            'sessions' => '2 – 4',
            'summary' => 'Limpieza bajo la encía para tratar la periodontitis: retira el sarro que causa sangrado, mal aliento y dientes flojos.',
            'before' => 'Ven con los dientes cepillados y habiendo comido, porque se hace con anestesia.',
            'aftercare' => "- No comas hasta que pase la anestesia.\n- Es normal sentir sensibilidad y algo de sangrado al cepillarte unos días.\n- Cepíllate suave, pero no dejes de hacerlo, y usa el enjuague que te indicó el doctor.\n- No fumes: retrasa la curación de la encía.",
        ],
        [
            'name' => 'Aplicación de flúor',
            'minutes' => 20,
            'sessions' => '1',
            'summary' => 'Barniz de flúor que fortalece el esmalte y previene las caries, sobre todo en niños.',
            'before' => 'Ven con los dientes cepillados.',
            'aftercare' => "- No comas ni tomes nada por 30 minutos.\n- Hoy evita lo duro, lo pegajoso y lo muy caliente.\n- No te cepilles hasta la noche, para que el flúor actúe.",
        ],
        [
            'name' => 'Sellantes',
            'minutes' => 30,
            'sessions' => '1',
            'summary' => 'Capa protectora en las muelas de los niños, para que la caries no entre por sus surcos.',
            'before' => 'Ven con los dientes cepillados.',
            'aftercare' => "- Puedes comer con normalidad.\n- Si sientes la mordida alta, avísanos para ajustarla.",
        ],
        [
            'name' => 'Pulpotomía',
            'minutes' => 45,
            'sessions' => '1',
            'summary' => 'Tratamiento del nervio en un diente de leche con caries profunda, para conservarlo hasta que salga el definitivo.',
            'before' => 'Ven habiendo comido y con los dientes cepillados.',
            'aftercare' => "- No comas hasta que pase la anestesia, para no morderte el labio o la mejilla.\n- Hoy come blando.\n- Toma solo lo que te recetó el doctor.",
        ],
        [
            'name' => 'Incrustación',
            'minutes' => 60,
            'sessions' => '2',
            'summary' => 'Pieza hecha a la medida para reconstruir una muela cuando la caries es muy grande para una resina.',
            'before' => 'Ven con los dientes cepillados y habiendo comido, porque puedes salir con la boca adormecida.',
            'aftercare' => "- No comas hasta que pase la anestesia.\n- Mientras tengas el provisional, evita lo duro y lo pegajoso de ese lado.\n- Si al morder la sientes alta, avísanos para ajustarla.",
        ],
        [
            'name' => 'Corona dental',
            'minutes' => 60,
            'sessions' => '2 – 3',
            'summary' => 'Funda de porcelana o zirconio que cubre un diente muy dañado y le devuelve su forma, su fuerza y su color.',
            'before' => 'Ven con los dientes cepillados y habiendo comido, porque puedes salir con la boca adormecida.',
            'aftercare' => "- No comas hasta que pase la anestesia.\n- Con la corona provisional, evita lo duro y lo pegajoso y mastica por el otro lado.\n- Si la corona provisional se sale, guárdala y escríbenos.\n- Si al morder sientes la corona alta, avísanos para ajustarla.",
        ],
        [
            'name' => 'Carillas dentales',
            'minutes' => 60,
            'sessions' => '2 – 3',
            'summary' => 'Láminas delgadas de porcelana o resina que se pegan al frente del diente para mejorar su forma y su color.',
            'before' => 'Ven con los dientes cepillados.',
            'aftercare' => "- No muerdas cosas duras con los dientes de adelante: uñas, lapiceros, hielo o huesos.\n- Cepíllate y usa hilo dental como siempre.\n- Si rechinas los dientes al dormir, cuéntaselo al doctor.",
        ],
        [
            'name' => 'Corona sobre implante',
            'minutes' => 60,
            'sessions' => '2 – 3',
            // Por su nombre sería un implante, con sus riesgos de cirugía: es la corona, y vuelve a control al año.
            'kind' => TreatmentKind::Other,
            'checkup_months' => 12,
            'summary' => 'El diente que va sobre tu implante, hecho a la medida cuando el implante ya se unió al hueso.',
            'before' => 'Ven con los dientes cepillados.',
            'aftercare' => "- Cepilla la corona como un diente más y limpia alrededor con hilo o cepillo interdental.\n- No muerdas cosas muy duras con ella.\n- Si la sientes floja o alta al morder, escríbenos.\n- Ven a tu control una vez al año.",
        ],
        [
            'name' => 'Prótesis removible',
            'minutes' => 45,
            'sessions' => '3 – 5',
            'summary' => 'Repone varios dientes perdidos con una prótesis que te pones y te quitas para limpiarla.',
            'before' => 'Ven con los dientes cepillados y trae tu prótesis anterior si tienes una.',
            'aftercare' => "- Quítate la prótesis para dormir y déjala en un vaso con agua.\n- Lávala con cepillo y jabón después de cada comida, no con pasta dental.\n- Los primeros días es normal sentirla rara o que roce.\n- Si te hace una herida, no la ajustes en casa: escríbenos.",
        ],
        [
            'name' => 'Prótesis total',
            'minutes' => 45,
            'sessions' => '4 – 6',
            'summary' => 'Dentadura completa para quien perdió todos los dientes de arriba, de abajo o de ambos.',
            'before' => 'Trae tu prótesis anterior si tienes una.',
            'aftercare' => "- Quítate la prótesis para dormir y déjala en un vaso con agua.\n- Lávala con cepillo y jabón después de cada comida, no con pasta dental.\n- Los primeros días es normal sentirla rara o que roce.\n- Si te hace una herida, no la ajustes en casa: escríbenos.",
        ],
        [
            'name' => 'Retenedores de ortodoncia',
            'minutes' => 30,
            'sessions' => '2',
            // Ya no lleva arco ni ligas, y no vuelve cada mes como quien tiene brackets.
            'kind' => TreatmentKind::Other,
            'checkup_months' => 6,
            'summary' => 'Mantienen tus dientes en su lugar cuando te retiran los brackets.',
            'before' => 'Ven con los dientes bien cepillados.',
            'aftercare' => "- Úsalos todo el tiempo que te indicó el doctor: sin ellos, los dientes se vuelven a mover.\n- Quítatelos para comer y lávalos con agua fría y jabón.\n- Guárdalos en su caja, lejos del calor y de las mascotas.\n- Si se rompen o ya no calzan, escríbenos.",
        ],
        [
            'name' => 'Placa para bruxismo',
            'minutes' => 30,
            'sessions' => '2',
            'summary' => 'Placa a la medida que usas al dormir si aprietas o rechinas los dientes: los protege del desgaste y relaja la mandíbula.',
            'before' => 'Ven con los dientes cepillados.',
            'aftercare' => "- Úsala todas las noches.\n- Al quitártela, lávala con agua fría y jabón y guárdala seca en su caja.\n- Los primeros días es normal salivar más o sentirla rara.\n- Tráela a tus controles para revisarla.",
        ],
        [
            'name' => 'Control y retiro de puntos',
            'minutes' => 20,
            'sessions' => '1',
            'summary' => 'Revisión de la herida y retiro de puntos después de una extracción o un implante.',
        ],
    ];

    /**
     * Lo que el catálogo trae para un tratamiento que se llama así, o null si no es uno de los suyos.
     *
     * @return Definition|null
     */
    public static function find(?string $name): ?array
    {
        $slug = Str::slug((string) $name);

        return collect([...self::BASE, ...self::MORE])->first(fn (array $definition) => Str::slug($definition['name']) === $slug);
    }

    /** Los tratamientos de siempre, para una instalación nueva. Los que ya están no se tocan. */
    public function installBase(): void
    {
        foreach (self::BASE as $position => $definition) {
            Treatment::firstOrCreate(['slug' => Str::slug($definition['name'])], [
                ...self::attributes($definition),
                'online_bookable' => $definition['bookable'] ?? false,
                'is_public' => true,
                'sort_order' => $position + 1,
            ]);
        }
    }

    /**
     * Los que el consultorio todavía no tiene, para ofrecerlos en "Agregar tratamientos sugeridos": [dirección => lo
     * que trae]. Uno apagado o borrado cuenta como que ya lo tiene, y también uno con el mismo nombre y otra dirección.
     *
     * @return array<string, Definition>
     */
    public function missing(): array
    {
        $existing = Treatment::withTrashed()->get(['name', 'slug']);
        $taken = $existing->pluck('slug')->merge($existing->map(fn (Treatment $treatment) => Str::slug($treatment->name)))->flip();

        return collect([...self::BASE, ...self::MORE])
            ->keyBy(fn (array $definition) => Str::slug($definition['name']))
            ->reject(fn (array $definition, string $slug) => $taken->has($slug))
            ->all();
    }

    /**
     * Agrega los elegidos. Quedan solo para el consultorio y sin precio, al final de la lista y con todos los doctores
     * activos marcados, como un tratamiento nuevo: después se les pone precio y se elige dónde aparecen.
     *
     * @param  list<string>  $slugs
     * @return Collection<int, Treatment>
     */
    public function add(array $slugs): Collection
    {
        $chosen = collect($this->missing())->only($slugs);

        return DB::transaction(function () use ($chosen): Collection {
            $last = (int) Treatment::withTrashed()->max('sort_order');
            $doctors = Doctor::query()->active()->pluck('id')->all();

            return $chosen->values()->map(function (array $definition, int $position) use ($last, $doctors): Treatment {
                $treatment = Treatment::create([
                    ...self::attributes($definition),
                    'slug' => Str::slug($definition['name']),
                    'online_bookable' => false,
                    'is_public' => false,
                    'sort_order' => $last + $position + 1,
                ]);
                $treatment->doctors()->attach($doctors);

                return $treatment;
            });
        });
    }

    /**
     * En una base que ya se usa: a los tratamientos que trajo el sistema les pone lo que antes no traían (antes de
     * venir, cuidados y el texto de su página), solo donde el consultorio no escribió nada. Va directo a la tabla,
     * sin pasar por el historial de cambios: no lo hizo nadie del consultorio.
     *
     * @return int Cuántos textos se llenaron.
     */
    public function fillEmptyTexts(): int
    {
        $filled = 0;

        foreach (self::BASE as $definition) {
            foreach (['before_instructions' => 'before', 'aftercare' => 'aftercare', 'description' => 'description'] as $column => $key) {
                if (blank($definition[$key] ?? null)) {
                    continue;
                }

                $filled += DB::table('treatments')
                    ->where('slug', Str::slug($definition['name']))
                    ->where(fn ($query) => $query->whereNull($column)->orWhere($column, ''))
                    ->update([$column => $definition[$key], 'updated_at' => now()]);
            }
        }

        return $filled;
    }

    /**
     * Lo que se guarda de una definición, sin lo que depende de cómo se agrega (dónde aparece y en qué orden).
     *
     * @param  Definition  $definition
     * @return array<string, mixed>
     */
    private static function attributes(array $definition): array
    {
        return [
            'name' => $definition['name'],
            'kind' => $definition['kind'] ?? null,
            'summary' => $definition['summary'],
            'description' => $definition['description'] ?? null,
            'duration_minutes' => $definition['minutes'],
            'sessions_label' => $definition['sessions'],
            'checkup_months' => $definition['checkup_months'] ?? null,
            'before_instructions' => $definition['before'] ?? null,
            'aftercare' => $definition['aftercare'] ?? null,
            'is_evaluation' => $definition['evaluation'] ?? false,
            'show_price' => true,
        ];
    }
}
