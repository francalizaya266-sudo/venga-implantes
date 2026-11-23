import { usePage } from '@/api/queries';
import type { PrivacyData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { Loading } from '@/components/ui/Loading';
import { ErrorPage } from '@/pages/ErrorPage';

/** Política de privacidad (Ley N.º 29733). */
export function PrivacyPage() {
  const { data, error } = usePage<PrivacyData>('pages/privacy');

  if (error) {
    return <ErrorPage error={error} />;
  }

  return <SiteLayout>{data ? <Privacy data={data.data} /> : <Loading />}</SiteLayout>;
}

function Privacy({ data: { clinic, address, whatsapp } }: { data: PrivacyData }) {
  return (
    <article className="stack-lg">
      <div className="stack">
        <h1>Política de privacidad</h1>
        <p className="lead">
          Cómo cuidamos tus datos personales y de salud, según la Ley N.º 29733 de Protección de Datos Personales y su
          reglamento.
        </p>
      </div>

      <section className="stack">
        <h2>Quién es responsable</h2>
        <p>
          {clinic}
          {address ? `, con dirección en ${address}` : ''}, es el titular del banco de datos de sus pacientes y
          responsable de su tratamiento.
        </p>
      </section>

      <section className="stack">
        <h2>Qué datos usamos</h2>
        <p>
          Tu nombre, celular, documento de identidad, fecha de nacimiento y correo; los de los familiares que tienes a
          cargo; tus citas y pagos; y tu historia clínica (antecedentes, odontograma, diagnósticos, tratamientos,
          radiografías, fotos clínicas y consentimientos). Los datos de salud son datos sensibles y se tratan con más
          cuidado.
        </p>
      </section>

      <section className="stack">
        <h2>Para qué los usamos</h2>
        <p>
          Para agendar y recordarte tus citas, atenderte, llevar tu historia clínica, emitir recetas y presupuestos y
          registrar tus pagos. Solo si lo aceptas, para enviarte promociones por WhatsApp; puedes darte de baja cuando
          quieras.
        </p>
      </section>

      <section className="stack">
        <h2>Quién los ve</h2>
        <p>
          Recepción ve tus datos de contacto y tus citas. Tu historia clínica solo la ven los odontólogos que te
          atienden. Cada consulta y cada cambio queda registrado con fecha y usuario, y nada se borra. No vendemos ni
          compartimos tus datos con terceros; se guardan en un servidor contratado por el consultorio con conexión
          cifrada y copias de seguridad.
        </p>
      </section>

      <section className="stack">
        <h2>Tu celular recordado</h2>
        <p>
          Cuando tocas el enlace que te enviamos por WhatsApp, guardamos en tu navegador una cookie que recuerda este
          celular para que no tengas que identificarte cada vez. Mientras lo sigas usando, Mi cuenta queda abierta en
          él; si pasas más de un año sin entrar, te pediremos un enlace nuevo. Puedes quitarla con "Salir en este
          celular" en Mi cuenta, y si pierdes tu celular, escríbenos y la cerramos en todos.
        </p>
      </section>

      <section className="stack">
        <h2>Cuánto tiempo los guardamos</h2>
        <p>
          La historia clínica se conserva el tiempo que exigen las normas de salud. Los demás datos, mientras seas
          paciente o hasta que nos pidas cancelarlos, si la ley lo permite.
        </p>
      </section>

      <section className="stack">
        <h2>Tus derechos</h2>
        <p>
          Puedes pedir acceder a tus datos, corregirlos, cancelarlos u oponerte a su uso (derechos ARCO), y retirar tu
          consentimiento para promociones. Escríbenos
          {whatsapp ? ` por WhatsApp al ${whatsapp}` : ''} o acércate al consultorio. Si no quedas conforme, puedes
          acudir a la Autoridad Nacional de Protección de Datos Personales.
        </p>
      </section>
    </article>
  );
}
