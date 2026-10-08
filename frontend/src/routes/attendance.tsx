import { createFileRoute } from '@tanstack/react-router';
import { Attendance } from '@/components/validity/workspace';
export const Route = createFileRoute('/attendance')({
 head: () => ({ meta: [{ title: 'Attendance — Validity' }, { name: 'description', content: 'Track sample usher arrivals and departures.' }, { property: 'og:title', content: 'Attendance — Validity' }, { property: 'og:description', content: 'Track sample usher arrivals and departures.' }, { property: 'og:type', content: 'website' }, { name: 'twitter:card', content: 'summary_large_image' }] }),
 component: Attendance,
});
