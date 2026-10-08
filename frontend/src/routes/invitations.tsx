import { createFileRoute } from '@tanstack/react-router';
import { Invitations } from '@/components/validity/workspace';
export const Route = createFileRoute('/invitations')({
 head: () => ({ meta: [{ title: 'Invitations — Validity' }, { name: 'description', content: 'Preview project invitations and usher responses.' }, { property: 'og:title', content: 'Invitations — Validity' }, { property: 'og:description', content: 'Preview project invitations and usher responses.' }, { property: 'og:type', content: 'website' }, { name: 'twitter:card', content: 'summary_large_image' }] }),
 component: Invitations,
});
