import {createFileRoute} from '@tanstack/react-router';
import {ProfileEditPage} from '@/components/validity/links';

export const Route = createFileRoute('/profile/$token')({
  head: () => ({
    meta: [
      { title: 'Update your profile — Validity' },
      { name: 'description', content: 'Correct your Validity Events usher profile details.' },
      { property: 'og:title', content: 'Update your profile — Validity' },
      { property: 'og:description', content: 'Correct your Validity usher profile.' },
      { property: 'og:type', content: 'website' },
      { name: 'twitter:card', content: 'summary' },
    ],
  }),
  component: function Page() {
    const { token } = Route.useParams();
    return <ProfileEditPage token={token} />;
  },
});
