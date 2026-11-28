import { useState } from 'react';
import { post } from '@/api/client';
import type { BookedAppointment, Clinic } from '@/api/types';
import { AppLink } from '@/components/ui/AppLink';
import { artUrl } from '@/lib/art';
import { paths } from '@/lib/paths';
import { whatsappUrl } from '@/lib/whatsapp';

interface Props {
  appointment: BookedAppointment;
  clinic: Clinic;
  howToPay: string;
}

/** Paso 4: listo. La cita quedó hecha: qué sigue, cómo guardarla y dónde es. */
export function Booked({ appointment, clinic, howToPay }: Props) {
  const [toldOrigin, setToldOrigin] = useState(false);
  const whatsapp = whatsappUrl(clinic, appointment.whatsapp_message);
  const { origin_url: originUrl } = appointment;

  function tellOrigin(origin: string) {
    setToldOrigin(true);

    if (originUrl) {
      // Es un dato que ayuda al consultorio, no al paciente: si no llega, no se le molesta con un error.
      post(originUrl, { origin }).catch(() => undefined);
    }
  }

  return (
    <section className="stack booked" aria-labelledby="listo">
      {/* La cita quedó hecha: lo dice el diente sonriente antes que el texto. */}
      <img src={artUrl('sonrisa')} alt="" width={450} height={410} className="done-art" />
      <h2 id="listo" className="step-title" tabIndex={-1}>
        ¡Tu cita quedó reservada!
      </h2>
      <div className="summary">
        <p className="what">{appointment.treatment}</p>
        <p style={{ margin: '4px 0 0' }}>{appointment.when}</p>
        <p className="muted" style={{ margin: '2px 0 0' }}>
          {appointment.who}
        </p>
        {appointment.price_line ? (
          <p className="price-line">
            {appointment.price_line} {howToPay}
          </p>
        ) : null}
      </div>
      {appointment.promo_notice ? (
        <div className="flash info">
          <p>{appointment.promo_notice}</p>
        </div>
      ) : null}

      <div className="stack">
        <h3>Qué sigue</h3>
        <ol className="next-steps">
          <li>
            <strong>Confirma tu asistencia.</strong>{' '}
            {appointment.remembered ? (
              // En su primera reserva, el WhatsApp trae su enlace de Mi cuenta; después, el de la cita. Con los dos la
              // confirma, y el número va a la vista para que note si lo escribió mal.
              <>
                Tu cita queda por confirmar. Confírmala en <AppLink href={paths.account}>Mi cuenta</AppLink>, desde este
                celular o con el enlace que te enviaremos por WhatsApp al {appointment.phone}.
              </>
            ) : (
              <>
                Tu cita queda por confirmar. Te escribiremos por WhatsApp al {appointment.phone} con un enlace para
                confirmarla, cambiar el horario o cancelar.
              </>
            )}
          </li>
          <li>
            <strong>Guárdala para no olvidarla.</strong> <a href={appointment.calendar_url}>Guardar en mi calendario</a>
          </li>
          {clinic.address ? (
            <li>
              <strong>Llega unos minutos antes.</strong> Estamos en {clinic.address}.{' '}
              {clinic.directions_url ? (
                <a href={clinic.directions_url} rel="noopener" target="_blank">
                  Cómo llegar
                </a>
              ) : null}
            </li>
          ) : null}
        </ol>
      </div>

      {!appointment.remembered && whatsapp ? (
        <>
          <p>¿Quieres adelantarte? Escríbenos y te respondemos con tu enlace.</p>
          <a href={whatsapp} className="btn whatsapp block" rel="noopener">
            Escribir por WhatsApp
          </a>
        </>
      ) : null}

      {/* Una carga nueva de la página: la otra reserva empieza de cero, sin la promoción ni lo elegido en esta. */}
      <a href={paths.booking()} className="btn quiet block">
        Reservar otra cita
      </a>

      {/* Con la cita ya reservada, y solo si quiere: un toque. Antes era una pregunta más del formulario. */}
      {toldOrigin ? (
        <p className="muted" role="status">
          ¡Gracias por contarnos!
        </p>
      ) : appointment.origins.length ? (
        <div className="panel stack origin-ask">
          <p className="label" id="como-nos-conociste">
            Una última cosa, si quieres: ¿cómo nos conociste?
          </p>
          <div className="chips wrap" role="group" aria-labelledby="como-nos-conociste">
            {appointment.origins.map((option) => (
              <button key={option.value} type="button" className="chip" onClick={() => tellOrigin(option.value)}>
                {option.label}
              </button>
            ))}
          </div>
        </div>
      ) : null}
    </section>
  );
}
