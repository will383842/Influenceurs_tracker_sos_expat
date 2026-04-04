import { useState, useEffect } from 'react';

/**
 * Like useState but persists value in localStorage.
 * Filters survive sidebar navigation (component unmount/remount).
 *
 * @param key      Unique localStorage key (e.g. 'biz_filterCountry')
 * @param initial  Default value if nothing stored yet
 */
export function usePersistentFilter<T>(key: string, initial: T): [T, React.Dispatch<React.SetStateAction<T>>] {
  const [value, setValue] = useState<T>(() => {
    try {
      const raw = localStorage.getItem(key);
      return raw !== null ? (JSON.parse(raw) as T) : initial;
    } catch {
      return initial;
    }
  });

  useEffect(() => {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch { /* quota exceeded or private mode */ }
  }, [key, value]);

  return [value, setValue];
}
