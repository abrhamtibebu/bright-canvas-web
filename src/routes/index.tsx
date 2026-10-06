import { createFileRoute } from '@tanstack/react-router';
import { Overview } from '@/components/validity/workspace';
export const Route = createFileRoute('/')({
 head: () => ({ meta: [{ title: 'Overview — Validity' }, { name: 'description', content: 'Your Validity people, projects and staffing overview.' }, { property: 'og:title', content: 'Overview — Validity' }, { property: 'og:description', content: 'Your Validity people, projects and staffing overview.' }, { property: 'og:type', content: 'website' }, { name: 'twitter:card', content: 'summary_large_image' }] }),
 component: Overview,
});
