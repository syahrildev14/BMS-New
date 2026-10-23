export function FieldError({ message }: { message?: string }) {
  if (!message) return null;

  return <p className="text-xs text-red-600 dark:text-red-400">{message}</p>;
}
