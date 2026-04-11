import { useQuery } from '@tanstack/react-query';
import api from '../api/client';
import { CONTACT_TYPES, mergeApiContactTypes } from '../lib/constants';
import type { ContactTypeConfig } from '../lib/constants';

/**
 * useContactTypes — loads contact types from /enums and merges into runtime constants.
 * Ensures new admin-created types (consulat, erasmus, etc.) are available app-wide.
 *
 * React Query-backed: cached indefinitely (staleTime: Infinity) since enums rarely change
 * and are re-hydrated from constants on every mount.
 */
export function useContactTypes() {
  const query = useQuery<ContactTypeConfig[]>({
    queryKey: ['enums', 'contact-types'],
    queryFn: async () => {
      const { data } = await api.get('/enums');
      if (data?.contact_types && Array.isArray(data.contact_types)) {
        mergeApiContactTypes(data.contact_types);
      }
      return [...CONTACT_TYPES];
    },
    staleTime: Infinity,
    gcTime: Infinity,
    retry: 0,
  });

  return query.data ?? CONTACT_TYPES;
}
