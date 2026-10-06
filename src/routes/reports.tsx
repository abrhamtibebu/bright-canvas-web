import { createFileRoute } from '@tanstack/react-router';
import { Reports } from '@/components/validity/workspace';
export const Route = createFileRoute('/reports')({
 head: () => ({ meta: [{ title: 'Reports & Ratings — Validity' }, { name: 'description', content: 'Explore staffing performance and event feedback.' }, { property: 'og:title', content: 'Reports & Ratings — Validity' }, { property: 'og:description', content: 'Explore staffing performance and event feedback.' }, { property: 'og:type', content: 'website' }, { name: 'twitter:card', content: 'summary_large_image' }] }),
 component: Reports,
});
