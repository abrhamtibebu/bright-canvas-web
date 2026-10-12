import { createFileRoute } from '@tanstack/react-router';
import { Payments } from '@/components/validity/workspace';

export const Route = createFileRoute('/payments/')({
  head: () => ({
    meta: [
      { title: 'Payments — Validity' },
      { name: 'description', content: 'Payment orders for confirmed ushers.' },
      { property: 'og:title', content: 'Payments — Validity' },
      { property: 'og:description', content: 'Payment orders for confirmed ushers.' },
      { property: 'og:type', content: 'website' },
      { name: 'twitter:card', content: 'summary_large_image' },
    ],
  }),
  component: Payments,
});
