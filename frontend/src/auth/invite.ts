/** True while a guest is on the way to a share link: login and registration then explain why. */
export function isInvited(state: unknown): boolean {
  return (state as { from?: string } | null)?.from?.startsWith('/einladung/') ?? false
}
