import React, { createContext, useContext, useEffect, useState } from 'react';
import { api } from './api';
import { useAuth } from './auth';

export type Catalog = Record<string, any>;

const CatalogContext = createContext<{ catalog: Catalog; loading: boolean }>({
  catalog: {},
  loading: false,
});

export function CatalogProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  const [catalog, setCatalog] = useState<Catalog>({});
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!user) {
      setCatalog({});
      return;
    }
    let active = true;
    setLoading(true);
    api<Catalog>('/catalog?lite=1')
      .then((next) => {
        if (active) setCatalog(next);
      })
      .catch(() => undefined)
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [user?.id]);

  return <CatalogContext.Provider value={{ catalog, loading }}>{children}</CatalogContext.Provider>;
}

export function useCatalog() {
  return useContext(CatalogContext);
}
