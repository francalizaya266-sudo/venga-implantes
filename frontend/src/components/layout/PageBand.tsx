import type { PageTexts } from '@/api/types';
import { Icon } from '@/components/ui/Icon';
import { artUrl } from '@/lib/art';

interface Props {
  texts: PageTexts;
  /** Mientras se reserva, en el celular la franja va corta (sin foto ni texto) y el primer paso queda a la vista. */
  brief: boolean;
}

/**
 * El encabezado de una página: frase corta, título en dos colores y texto, como se editan en el panel. La foto,
 * a la derecha, se funde con el celeste; el diente de cristal va entre el texto y la foto.
 */
export function PageBand({ texts, brief }: Props) {
  const [start, accent, end] = texts.title;

  return (
    <div className={['page-band', texts.photo_url ? 'with-photo' : '', brief ? 'brief' : ''].filter(Boolean).join(' ')}>
      {texts.photo_url ? (
        <figure className="band-photo">
          <img src={texts.photo_url} alt="" fetchPriority="high" />
        </figure>
      ) : null}
      <div className="wrap wide">
        <div className="band-text">
          {texts.kicker ? (
            <p className="kicker">
              <span>
                <Icon name={texts.icon} />
              </span>
              {texts.kicker}
            </p>
          ) : null}
          <h1>
            {start}
            {accent !== '' ? <em>{accent}</em> : null}
            {end}
          </h1>
          {texts.lead ? <p className="lead">{texts.lead}</p> : null}
        </div>
        <img
          src={artUrl('cristal-diente-360')}
          srcSet={`${artUrl('cristal-diente-360')} 360w, ${artUrl('cristal-diente')} 720w`}
          sizes="(min-width: 900px) 260px, 150px"
          width={720}
          height={720}
          alt=""
          className="band-art"
        />
      </div>
    </div>
  );
}
