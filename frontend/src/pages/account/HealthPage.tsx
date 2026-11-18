import { useEffect, useRef, useState, type FormEvent } from 'react';
import { useParams } from 'react-router';
import { useAction } from '@/api/actions';
import { ApiError, apiUrl } from '@/api/client';
import { usePage } from '@/api/queries';
import type { HealthData } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { AppLink } from '@/components/ui/AppLink';
import { Loading } from '@/components/ui/Loading';
import { useLeave } from '@/lib/leave';
import { paths } from '@/lib/paths';
import { ErrorPage } from '@/pages/ErrorPage';

/**
 * "Antes de tu cita": la salud del paciente en dos minutos, desde el celular recordado. Solo se llena (no
 * muestra lo ya registrado). El doctor lo ve en la ficha como "lo llenó el paciente" y lo confirma.
 */
export function HealthPage() {
  const { id = '' } = useParams();
  const url = apiUrl(`account/patients/${id}/health`);
  const { data, error } = usePage<HealthData>(url);
  const leave = useLeave();
  const left = useRef(false);
  const known = error instanceof ApiError && error.code === 'already_known';
  // El consultorio ya tiene los antecedentes: no hay nada que llenar y se vuelve a Mi cuenta con el aviso. Con el
  // formulario a la vista no aplica: es el propio paciente quien acaba de enviarlos.
  const alreadyKnown = known && !data ? error.message : null;

  useEffect(() => {
    if (alreadyKnown && !left.current) {
      left.current = true;
      leave(paths.account, alreadyKnown, { replace: true });
    }
  }, [alreadyKnown, leave]);

  if (error && !known) {
    return <ErrorPage error={error} />;
  }

  return (
    <SiteLayout whatsappFloat={false} minimalFooter>
      {data ? <HealthForm key={data.data.patient_id} health={data.data} url={url} /> : <Loading />}
    </SiteLayout>
  );
}

type Choice = 'yes' | 'no' | null;

function HealthForm({ health, url }: { health: HealthData; url: string }) {
  const { run, busy, errors, fix } = useAction();
  const leave = useLeave();
  const [choices, setChoices] = useState<Record<string, Choice>>({});
  const [details, setDetails] = useState<Record<string, string>>({});
  const [conditions, setConditions] = useState<string[]>([]);
  const [also, setAlso] = useState<string[]>([]);
  const [notes, setNotes] = useState('');
  const [rejected, setRejected] = useState(0);
  const form = useRef<HTMLFormElement>(null);

  // Lo que faltó puede haber quedado arriba, fuera de la pantalla: se lleva al paciente al primer campo.
  useEffect(() => {
    if (rejected) {
      form.current?.querySelector('.error')?.closest('.field')?.querySelector('input')?.focus();
    }
  }, [rejected]);

  function toggle(list: string[], key: string, checked: boolean): string[] {
    return checked ? [...list, key] : list.filter((other) => other !== key);
  }

  function send(event: FormEvent) {
    event.preventDefault();

    const answers: Record<string, unknown> = { conditions, notes };

    for (const question of health.questions) {
      answers[question.choice] = choices[question.choice] ?? null;
      answers[question.detail] = details[question.detail] ?? '';
    }

    for (const option of health.also) {
      answers[option.key] = also.includes(option.key);
    }

    void run(url, answers, { onDone: (message) => leave(paths.account, message, { replace: true }) }).then((sent) => {
      if (!sent) {
        setRejected((times) => times + 1);
      }
    });
  }

  return (
    <section className="stack-lg health">
      <div className="stack">
        <AppLink href={paths.account} className="link-btn" style={{ textDecoration: 'none' }}>
          ← Volver a Mi cuenta
        </AppLink>
        <h1>{health.title}</h1>
        <p className="lead">{health.lead}</p>
      </div>

      <form ref={form} className="stack-lg health-form" noValidate onSubmit={send}>
        {health.questions.map((question) => (
          <fieldset key={question.choice} className="field health-question">
            <legend className="label">{question.question}</legend>
            <span className="hint">{question.hint}</span>
            <div className="segmented">
              {(['no', 'yes'] as const).map((value) => (
                <label key={value}>
                  <input
                    type="radio"
                    name={question.choice}
                    value={value}
                    checked={choices[question.choice] === value}
                    onChange={() => {
                      setChoices((current) => ({ ...current, [question.choice]: value }));
                      fix(question.choice);
                    }}
                  />
                  <span>{value === 'no' ? 'No' : 'Sí'}</span>
                </label>
              ))}
            </div>
            {errors[question.choice] ? <p className="error">{errors[question.choice]}</p> : null}
            <div className="field health-detail">
              <label htmlFor={question.detail}>{question.label}</label>
              <input
                id={question.detail}
                name={question.detail}
                className="input"
                maxLength={300}
                placeholder={question.placeholder}
                value={details[question.detail] ?? ''}
                onChange={(event) => {
                  setDetails((current) => ({ ...current, [question.detail]: event.target.value }));
                  fix(question.detail);
                }}
                aria-invalid={errors[question.detail] ? true : undefined}
              />
              {errors[question.detail] ? <p className="error">{errors[question.detail]}</p> : null}
            </div>
          </fieldset>
        ))}

        <fieldset className="field">
          <legend className="label">{health.conditions_question}</legend>
          <span className="hint">Marca las que correspondan. Si ninguna, sigue de largo.</span>
          <div className="health-conditions">
            {health.conditions.map((condition) => (
              <label key={condition.key} className="check">
                <input
                  type="checkbox"
                  name="conditions[]"
                  value={condition.key}
                  checked={conditions.includes(condition.key)}
                  onChange={(event) => setConditions((current) => toggle(current, condition.key, event.target.checked))}
                />
                <span>{condition.label}</span>
              </label>
            ))}
          </div>
        </fieldset>

        {health.also.length ? (
          <fieldset className="field">
            <legend className="label">También</legend>
            <div className="health-conditions">
              {health.also.map((option) => (
                <label key={option.key} className="check">
                  <input
                    type="checkbox"
                    name={option.key}
                    value="1"
                    checked={also.includes(option.key)}
                    onChange={(event) => setAlso((current) => toggle(current, option.key, event.target.checked))}
                  />
                  <span>{option.label}</span>
                </label>
              ))}
            </div>
          </fieldset>
        ) : null}

        <div className="field">
          <label htmlFor="notes">¿Algo más que el doctor deba saber?</label>
          <span className="hint">{health.notes_hint}</span>
          <textarea
            id="notes"
            name="notes"
            className="input"
            rows={3}
            maxLength={500}
            value={notes}
            onChange={(event) => setNotes(event.target.value)}
          />
        </div>

        <div className="stack">
          <button type="submit" className="btn block" disabled={busy}>
            Enviar al consultorio
          </button>
          <p className="muted small">Si algo cambia antes de la cita, cuéntaselo al doctor ese día.</p>
        </div>
      </form>
    </section>
  );
}
