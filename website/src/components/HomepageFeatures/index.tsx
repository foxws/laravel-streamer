import type {ReactNode} from 'react';
import clsx from 'clsx';
import Heading from '@theme/Heading';
import styles from './styles.module.css';

type FeatureItem = {
  title: string;
  description: ReactNode;
};

const FeatureList: FeatureItem[] = [
  {
    title: 'Fluent API',
    description: (
      <>
        Laravel-style chainable methods for packaging adaptive streaming
        content, with cross-disk workflows built in for local, S3, and other
        Laravel filesystems.
      </>
    ),
  },
  {
    title: 'Adaptive bitrate & AES encryption',
    description: (
      <>
        Create multi-quality HLS and DASH streams, with built-in AES content
        protection and optional key rotation.
      </>
    ),
  },
  {
    title: 'Dynamic manifests',
    description: (
      <>
        Rewrite HLS playlists and DASH MPDs with signed URLs at serve-time,
        and hook into <code>StreamingStarted</code>,{' '}
        <code>StreamingCompleted</code>, and <code>StreamingFailed</code>{' '}
        events.
      </>
    ),
  },
];

function Feature({title, description}: FeatureItem) {
  return (
    <div className={clsx('col col--4')}>
      <div className="text--center padding-horiz--md">
        <Heading as="h3">{title}</Heading>
        <p>{description}</p>
      </div>
    </div>
  );
}

export default function HomepageFeatures(): ReactNode {
  return (
    <section className={styles.features}>
      <div className="container">
        <div className="row">
          {FeatureList.map((props, idx) => (
            <Feature key={idx} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}
