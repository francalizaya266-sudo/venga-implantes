import type { Faq } from '@/api/types';
import { Html } from '@/components/ui/Html';

export function Faqs({ faqs }: { faqs: Faq[] }) {
  return (
    <div className="faqs">
      {faqs.map((faq) => (
        <details key={faq.id}>
          <summary>{faq.question}</summary>
          <Html className="prose" html={faq.answer_html} />
        </details>
      ))}
    </div>
  );
}
