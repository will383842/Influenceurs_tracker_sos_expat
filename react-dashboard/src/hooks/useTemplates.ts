import { useCallback, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../api/client';
import type { EmailTemplate, OutreachMessage, ContactType } from '../types/influenceur';

interface TemplateFilters {
  contact_type?: ContactType;
  language?: string;
}

/**
 * useTemplates — React Query-backed email templates.
 * Public API preserved: { templates, loading, load, create, update, remove, generateForContact, generateBatch }.
 */
export function useTemplates() {
  const qc = useQueryClient();
  const [filters, setFilters] = useState<TemplateFilters>({});

  const query = useQuery<EmailTemplate[]>({
    queryKey: ['templates', filters],
    queryFn: async () => {
      const { data } = await api.get<EmailTemplate[]>('/templates', { params: filters });
      return data;
    },
    enabled: false,
    staleTime: 60_000,
  });

  const invalidate = useCallback(() => {
    qc.invalidateQueries({ queryKey: ['templates'] });
  }, [qc]);

  const createMutation = useMutation({
    mutationFn: async (payload: Partial<EmailTemplate>) => {
      const { data } = await api.post<EmailTemplate>('/templates', payload);
      return data;
    },
    onSuccess: invalidate,
  });

  const updateMutation = useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: Partial<EmailTemplate> }) => {
      const { data } = await api.put<EmailTemplate>(`/templates/${id}`, payload);
      return data;
    },
    onSuccess: invalidate,
  });

  const removeMutation = useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/templates/${id}`);
      return id;
    },
    onSuccess: invalidate,
  });

  const load = useCallback(
    async (contactType?: ContactType, language?: string) => {
      const next: TemplateFilters = {};
      if (contactType) next.contact_type = contactType;
      if (language) next.language = language;
      setFilters(next);
      await query.refetch();
    },
    [query],
  );

  const create = useCallback(
    (payload: Partial<EmailTemplate>) => createMutation.mutateAsync(payload),
    [createMutation],
  );
  const update = useCallback(
    (id: number, payload: Partial<EmailTemplate>) => updateMutation.mutateAsync({ id, payload }),
    [updateMutation],
  );
  const remove = useCallback((id: number) => removeMutation.mutateAsync(id), [removeMutation]);

  const generateForContact = useCallback(
    async (influenceurId: number, step = 1): Promise<OutreachMessage | null> => {
      try {
        const { data } = await api.get<OutreachMessage>(`/contacts/${influenceurId}/outreach`, { params: { step } });
        return data;
      } catch {
        return null;
      }
    },
    [],
  );

  const generateBatch = useCallback(async (ids: number[], step = 1): Promise<OutreachMessage[]> => {
    try {
      const { data } = await api.post<OutreachMessage[]>('/templates/generate-batch', {
        influenceur_ids: ids,
        step,
      });
      return data;
    } catch {
      return [];
    }
  }, []);

  return {
    templates: query.data ?? [],
    loading: query.isFetching,
    load,
    create,
    update,
    remove,
    generateForContact,
    generateBatch,
  };
}
