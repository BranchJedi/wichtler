import { useQueryClient } from '@tanstack/react-query'
import { useCallback } from 'react'
import type { UserResponse } from '../api/generated/model'
import { getGetMeQueryKey, useGetMe } from '../api/generated/me/me'

/** The signed-in user, `null` for guests and `undefined` while the first check is running. */
export function useSession(): { user: UserResponse | null | undefined } {
  const { data, isPending, isError } = useGetMe({
    query: { retry: false, staleTime: 5 * 60 * 1000, refetchOnWindowFocus: false },
  })

  if (isPending) return { user: undefined }
  return { user: isError ? null : data }
}

/** Login, registration and profile changes answer with the user; this stores it without another request. */
export function useSetSessionUser(): (user: UserResponse) => void {
  const queryClient = useQueryClient()
  return useCallback((user) => queryClient.setQueryData(getGetMeQueryKey(), user), [queryClient])
}
