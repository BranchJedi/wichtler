import { useGetHealth } from './api/generated/system/system'

// Stage 1 placeholder: proves the chain Symfony -> OpenAPI -> orval -> React end to end.
function App() {
  const { data, isPending, isError } = useGetHealth()

  let status = 'API wird geprüft …'
  if (isError) {
    status = 'API nicht erreichbar'
  } else if (!isPending) {
    status = data.data.database ? 'API und Datenbank erreichbar' : 'API erreichbar, Datenbank nicht'
  }

  return (
    <main>
      <h1>Wichtler</h1>
      <p role="status">{status}</p>
    </main>
  )
}

export default App
