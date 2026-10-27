import {createFileRoute} from '@tanstack/react-router';
import {InvitePage} from '@/components/validity/links';

export const Route = createFileRoute('/invite/$token')({
  head: () => ({
    meta: [
      { title: 'Check your profile — Validity' },
      { name: 'description', content: 'Check whether you already have a Validity Events usher profile.' },
      { property: 'og:title', content: 'Check your profile — Validity' },
      { property: 'og:description', content: 'Check your Validity usher profile, or create one.' },
      { property: 'og:type', content: 'website' },
      { name: 'twitter:card', content: 'summary' },
    ],
  }),
  component: function Page() {
    const { token } = Route.useParams();
    return <InvitePage token={token} />;
  },
});
