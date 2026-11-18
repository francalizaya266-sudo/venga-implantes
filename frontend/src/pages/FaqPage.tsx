import { useMemo, useState } from 'react';
import { usePage } from '@/api/queries';
import type { FaqsData, FaqTopic } from '@/api/types';
import { SiteLayout } from '@/components/layout/SiteLayout';
import { AppLink } from '@/components/ui/AppLink';
import { TreatmentArt } from '@/components/ui/Art';
import { Html } from '@/components/ui/Html';
import { Icon } from '@/components/ui/Icon';
import { Loading } from '@/components/ui/Loading';
import { paths } from '@/lib/paths';
import { plain, textOf } from '@/lib/text';
import { ErrorPage } from '@/pages/ErrorPage';

/** Preguntas frecuentes. Quien no encuentra su duda tiene el WhatsApp en la franja que cierra la página. */
export function FaqPage() {
  const { data, error } = usePage<FaqsData>('faqs');

  if (error) {
    return <ErrorPage error={error} />;
  }

  return (
    <SiteLayout page="preguntas" wide>
      {data ? (
        data.data.topics.length ? (
          <FaqBrowser topics={data.data.topics} />
        ) : (
          <p className="panel">Pronto publicaremos las preguntas más comunes.</p>
        )
      ) : (
        <Loading />
      )}
    </SiteLayout>
  );
}

/**
 * El buscador y los temas dejan a la vista solo lo que se busca, uno a la vez: escribir busca en todas las
 * preguntas; elegir un tema limpia lo escrito.
 */
function FaqBrowser({ topics }: { topics: FaqTopic[] }) {
  const [search, setSearch] = useState('');
  const [topic, setTopic] = useState('');
  const severalTopics = topics.length > 1;

  // Lo que se busca en cada pregunta: ella y su respuesta, sin tildes ni mayúsculas.
  const texts = useMemo(
    () =>
      new Map(
        topics.flatMap((group) =>
          group.faqs.map((faq) => [faq.id, plain(`${faq.question} ${textOf(faq.answer_html)}`)]),
        ),
      ),
    [topics],
  );
  const words = plain(search).split(/\s+/).filter(Boolean);
  const matches = (group: FaqTopic, faqId: number) =>
    (topic === '' || group.id === topic) && words.every((word) => texts.get(faqId)?.includes(word));
  const found = topics.some((group) => group.faqs.some((faq) => matches(group, faq.id)));

  function choose(id: string) {
    setSearch('');
    setTopic(id);
  }

  return (
    <div className={`faq-page${severalTopics ? ' with-topics' : ''}`}>
      <div className="faq-search" role="search">
        <Icon name="search" />
        <label htmlFor="buscar-pregunta" className="sr-only">
          Buscar una pregunta
        </label>
        <input
          type="search"
          id="buscar-pregunta"
          placeholder="Buscar una pregunta…"
          autoComplete="off"
          enterKeyHint="search"
          value={search}
          onChange={(event) => {
            setSearch(event.target.value);
            setTopic('');
          }}
        />
      </div>

      {severalTopics ? (
        <nav className="faq-topics" aria-labelledby="temas">
          <h2 id="temas">Temas</h2>
          <ul>
            <li>
              <a
                href="#preguntas"
                aria-current={topic === '' ? 'true' : undefined}
                onClick={(event) => {
                  event.preventDefault();
                  choose('');
                }}
              >
                <span className="topic-icon">
                  <Icon name="chat-check" />
                </span>
                Todas
              </a>
            </li>
            {topics.map((group) => (
              <li key={group.id}>
                <a
                  href={`#${group.id}`}
                  aria-current={topic === group.id ? 'true' : undefined}
                  onClick={(event) => {
                    event.preventDefault();
                    choose(group.id);
                  }}
                >
                  <span className="topic-icon">
                    {group.treatment ? <TreatmentArt art={group.treatment.art} size={32} /> : <Icon name="question" />}
                  </span>
                  {group.name}
                </a>
              </li>
            ))}
          </ul>
        </nav>
      ) : null}

      <div className="faq-list" id="preguntas">
        {topics.map((group) => (
          <section
            key={group.id}
            className="faq-group"
            id={group.id}
            aria-label={group.name}
            hidden={!group.faqs.some((faq) => matches(group, faq.id))}
          >
            {/* Con un solo tema, su título sobra: las preguntas van directo. */}
            {severalTopics ? (
              <h2>
                {group.treatment ? (
                  <AppLink href={paths.treatment(group.treatment.slug)}>{group.name}</AppLink>
                ) : (
                  group.name
                )}
              </h2>
            ) : null}
            <div className="faqs">
              {group.faqs.map((faq) => (
                <details key={faq.id} hidden={!matches(group, faq.id)}>
                  <summary>{faq.question}</summary>
                  <Html className="prose" html={faq.answer_html} />
                </details>
              ))}
            </div>
          </section>
        ))}
        <p className="panel faq-none" role="status" hidden={found}>
          No encontramos preguntas con esas palabras. Prueba con otras.
        </p>
      </div>
    </div>
  );
}
