import {createFileRoute} from '@tanstack/react-router';
import {RegistrationPage} from '@/components/validity/links';

export const Route = createFileRoute('/register/$token')({
  head: () => ({
    meta: [
      { title: 'Usher registration — Validity' },
      { name: 'description', content: 'Register to join the Validity Event & Marketing usher community.' },
      { property: 'og:title', content: 'Usher registration — Validity' },
      { property: 'og:description', content: 'Register to join the Validity usher community.' },
      { property: 'og:type', content: 'website' },
      { name: 'twitter:card', content: 'summary' },
    ],
  }),
  component: function Page() {
    const { token } = Route.useParams();
    return <RegistrationPage token={token} />;
  },
});
