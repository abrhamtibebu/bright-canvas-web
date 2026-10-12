import { createFileRoute } from '@tanstack/react-router';
import { PaymentSheet } from '@/components/validity/workspace';

export const Route = createFileRoute('/payments/$id')({
  head: () => ({
    meta: [
      { title: 'Payment order — Validity' },
      { name: 'description', content: 'Mark usher payments paid or unpaid.' },
      { property: 'og:title', content: 'Payment order — Validity' },
      { property: 'og:description', content: 'Mark usher payments paid or unpaid.' },
      { property: 'og:type', content: 'website' },
      { name: 'twitter:card', content: 'summary_large_image' },
    ],
  }),
  component: PaymentSheetRoute,
});

function PaymentSheetRoute() {
  const { id } = Route.useParams();
  return <PaymentSheet id={id} />;
}
