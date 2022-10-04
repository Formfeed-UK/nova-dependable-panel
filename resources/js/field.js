import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'

Nova.booting((app, store) => {
  app.component('index-nova-dependable-panel', IndexField)
  app.component('detail-nova-dependable-panel', DetailField)
  app.component('form-nova-dependable-panel', FormField)
})
