import { createFileRoute } from '@tanstack/react-router';
import { Directory } from '@/components/validity/workspace';
export const Route = createFileRoute('/directory')({
 head: () => ({ meta: [{ title: 'Usher Directory — Validity' }, { name: 'description', content: 'Search and explore the Validity demo usher community.' }, { property: 'og:title', content: 'Usher Directory — Validity' }, { property: 'og:description', content: 'Search and explore the Validity demo usher community.' }, { property: 'og:type', content: 'website' }, { name: 'twitter:card', content: 'summary_large_image' }] }),
 component: Directory,
});
