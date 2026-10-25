export type SmsStatus = {
  queued: boolean | number;
  skipped?: number;
  error?: string | null;
};

export function smsSummary(sms: SmsStatus | null | undefined): string {
  if (!sms) {
    return "";
  }

  const queued = typeof sms.queued === "boolean" ? (sms.queued ? 1 : 0) : sms.queued;
  const skipped = sms.skipped ?? 0;

  if (queued > 0) {
    const queuedText =
      typeof sms.queued === "boolean"
        ? "SMS queued to their phone."
        : `SMS queued for ${queued} usher${queued === 1 ? "" : "s"}.`;
    const skipText = skipped > 0 ? ` ${skipped} skipped (no phone).` : "";
    const errorText = sms.error ? ` ${sms.error}` : "";
    return `${queuedText}${skipText}${errorText}`.trim();
  }

  if (sms.error) {
    return sms.error;
  }

  if (skipped > 0) {
    return `${skipped} skipped (no phone).`;
  }

  return "SMS was not sent.";
}
