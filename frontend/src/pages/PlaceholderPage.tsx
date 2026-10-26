export default function PlaceholderPage({ title, task }: { title: string; task: string }) {
  return (
    <div>
      <h1 className="text-2xl font-semibold">{title}</h1>
      <p className="mt-2 text-slate-500">Dikerjakan di {task}.</p>
    </div>
  )
}
