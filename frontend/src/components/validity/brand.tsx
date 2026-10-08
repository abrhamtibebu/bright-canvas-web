import { useEffect, useState } from "react";
import { authorizedBlob } from "@/lib/api";
import { initials, type Usher } from "@/lib/demo-data";

export function LoadingMark({ label = "Loading…" }: { label?: string }) {
  return (
    <div className="loading-mark" role="status">
      <span className="logo-mark" aria-hidden="true">
        <img src="/validity-events-logo.svg" alt="" />
      </span>
      <p>{label}</p>
    </div>
  );
}

export function UsherAvatar({ usher }: { usher: Usher }) {
  const photo = usePhoto(usher.photos?.[0]);
  if (photo.src) return <img className="person-avatar photo" src={photo.src} alt="" />;
  return <div className="person-avatar">{initials(usher.name)}</div>;
}

export function UsherPhotos({ usher }: { usher: Usher }) {
  const photos = usher.photos ?? [];
  if (!photos.length) return null;
  return (
    <div className="photo-grid">
      {photos.map((path) => (
        <PhotoTile key={path} path={path} alt={`${usher.name} photo`} />
      ))}
    </div>
  );
}

function PhotoTile({ path, alt }: { path: string; alt: string }) {
  const photo = usePhoto(path);
  if (photo.state === "missing") return null;
  if (!photo.src) return <div className="photo-tile" aria-hidden="true" />;
  return <img className="photo-tile" src={photo.src} alt={alt} />;
}

function usePhoto(path: string | undefined) {
  const [src, setSrc] = useState<string | null>(null);
  const [state, setState] = useState<"loading" | "ready" | "missing">(path ? "loading" : "missing");
  useEffect(() => {
    if (!path) return;
    let active = true;
    let url: string | null = null;
    void authorizedBlob(path).then((next) => {
      if (!active) {
        if (next) URL.revokeObjectURL(next);
        return;
      }
      if (!next) {
        setState("missing");
        return;
      }
      url = next;
      setSrc(next);
      setState("ready");
    });
    return () => {
      active = false;
      if (url) URL.revokeObjectURL(url);
    };
  }, [path]);
  return { src, state };
}
