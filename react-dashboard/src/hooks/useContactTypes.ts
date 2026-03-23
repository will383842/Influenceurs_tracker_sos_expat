import { useEffect, useState } from 'react';
import api from '../api/client';
import { CONTACT_TYPES, mergeApiContactTypes } from '../lib/constants';
import type { ContactTypeConfig } from '../lib/constants';

let loaded = false;

/**
 * Hook that loads contact types from the API and merges them into the runtime constants.
 * This ensures new types created in admin console (consulat, erasmus, etc.)
 * are immediately available everywhere in the frontend.
 *
 * Call this once in the root layout — all components using CONTACT_TYPES
 * or getContactType() will automatically see the merged types.
 */
export function useContactTypes() {
  const [types, setTypes] = useState<ContactTypeConfig[]>(CONTACT_TYPES);

  useEffect(() => {
    if (loaded) {
      setTypes(CONTACT_TYPES);
      return;
    }

    api.get('/enums')
      .then(({ data }) => {
        if (data.contact_types && Array.isArray(data.contact_types)) {
          mergeApiContactTypes(data.contact_types);
          setTypes([...CONTACT_TYPES]);
          loaded = true;
        }
      })
      .catch(() => {
        // Use defaults on error
      });
  }, []);

  return types;
}
