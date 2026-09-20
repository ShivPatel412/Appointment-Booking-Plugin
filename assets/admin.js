(function (wp) {
  'use strict';
  const { createElement: h, useEffect, useMemo, useState } = wp.element;
  const apiFetch = wp.apiFetch;
  apiFetch.use(apiFetch.createNonceMiddleware(window.ABP_CONFIG.nonce));
  const base = '/appointment-booking/v1';
  const statuses = ['pending', 'approved', 'cancelled', 'rejected', 'completed', 'no-show'];
  const pages = [
    ['dashboard', 'Dashboard', 'chart-bar'], ['calendar', 'Calendar', 'calendar-alt'],
    ['bookings', 'Bookings', 'book'], ['doctors', 'Employees / Doctors', 'businessperson'],
    ['catalog', 'Catalog', 'screenoptions'], ['patients', 'Customers', 'groups'],
    ['notifications', 'Notifications', 'email'], ['customize', 'Customize', 'admin-customizer'],
    ['fields', 'Custom Fields', 'forms'], ['integrations', 'Features & Integrations', 'admin-plugins'],
    ['settings', 'Settings', 'admin-generic']
  ];
  const request = (path, options) => apiFetch(Object.assign({ path: base + path }, options || {}));
  const today = () => new Date().toISOString().slice(0, 10);
  const dateShift = (date, days) => { const d = new Date(date + 'T12:00:00'); d.setDate(d.getDate() + days); return d.toISOString().slice(0, 10); };
  const input = (label, value, set, type, extra) => h('label', { className: 'abp-field' }, h('span', null, label), h('input', Object.assign({ type: type || 'text', value: value == null ? '' : value, onChange: e => set(e.target.value) }, extra || {})));
  const select = (label, value, set, options) => h('label', { className: 'abp-field' }, h('span', null, label), h('select', { value: value == null ? '' : value, onChange: e => set(e.target.value) }, h('option', { value: '' }, 'Select…'), options.map(o => h('option', { key: o.value, value: o.value }, o.label))));
  function MediaField({ label, value, onChange }) {
    const choose = () => { const frame = wp.media({ title: 'Choose image', library: { type: 'image' }, multiple: false }); frame.on('select', () => onChange(frame.state().get('selection').first().toJSON().id)); frame.open(); };
    return h('div', { className: 'abp-field' }, h('span', null, label), h('div', { className: 'abp-media-field' }, h('input', { value: value || '', readOnly: true, placeholder: 'No image selected' }), h('button', { type: 'button', onClick: choose }, 'Choose'), value ? h('button', { type: 'button', onClick: () => onChange('') }, 'Clear') : null));
  }

  function Notice({ notice, clear }) {
    if (!notice) return null;
    return h('div', { className: 'abp-notice ' + (notice.type || 'error'), role: 'alert' }, h('span', null, notice.text), h('button', { onClick: clear, 'aria-label': 'Dismiss' }, '×'));
  }

  function Dashboard() {
    const [data, setData] = useState(null); const [from, setFrom] = useState(today().slice(0, 8) + '01'); const [to, setTo] = useState(today());
    useEffect(() => { request('/dashboard?from=' + from + '&to=' + to).then(setData); }, [from, to]);
    if (!data) return h('p', null, 'Loading dashboard…');
    const cards = [['total', 'Total bookings'], ['today', "Today's bookings"], ['upcoming', 'Upcoming'], ['pending', 'Pending'], ['patients', 'Patients'], ['doctors', 'Doctors']];
    return h('section', null,
      h('div', { className: 'abp-page-head' }, h('div', null, h('h1', null, 'Dashboard'), h('p', null, 'A live view of your appointment activity.')), h('div', { className: 'abp-inline' }, input('From', from, setFrom, 'date'), input('To', to, setTo, 'date'))),
      h('div', { className: 'abp-stats' }, cards.map(([key, label]) => h('article', { key }, h('span', null, label), h('strong', null, Number(data.counts[key] || 0))))),
      h('div', { className: 'abp-grid-two' },
        h('article', { className: 'abp-panel' },
          h('h2', null, 'Booking activity'),
          data.activity.length
            ? h('div', { className: 'abp-chart' }, data.activity.map(row => h('div', { key: row.day, title: row.day + ': ' + row.total }, h('i', { style: { height: Math.max(8, Number(row.total) * 12) + 'px' } }), h('small', null, row.day.slice(5)))))
            : h('p', { className: 'abp-empty' }, 'No activity in this date range.')
        ),
        h('article', { className: 'abp-panel' },
          h('h2', null, 'Recent bookings'),
          data.recent.length
            ? h('ul', { className: 'abp-feed' }, data.recent.map(row => h('li', { key: row.id }, h('b', null, row.patient_name), h('span', null, row.service_name + ' · ' + row.start_at))))
            : h('p', { className: 'abp-empty' }, 'No bookings yet.')
        )
      )
    );
  }

  function SimpleCrud({ resource, title, fields, columns, deactivate }) {
    const [rows, setRows] = useState([]); const [editing, setEditing] = useState(null); const [search, setSearch] = useState(''); const [notice, setNotice] = useState(null);
    const load = () => request('/' + resource + (search ? '?search=' + encodeURIComponent(search) : '')).then(setRows).catch(e => setNotice({ text: e.message }));
    useEffect(load, [resource, search]);
    const remove = row => { if (!window.confirm((deactivate ? 'Deactivate ' : 'Delete ') + (row.name || row.first_name) + '?')) return; request('/' + resource + '/' + row.id, { method: 'DELETE' }).then(load).catch(e => setNotice({ text: e.message })); };
    return h('section', null,
      h('div', { className: 'abp-page-head' }, h('div', null, h('h1', null, title), h('p', null, 'Create, review, and maintain records.')), h('button', { className: 'abp-primary', onClick: () => setEditing({ active: 1 }) }, '+ Add new')),
      h(Notice, { notice, clear: () => setNotice(null) }),
      h('div', { className: 'abp-toolbar' }, h('input', { type: 'search', value: search, placeholder: 'Search…', onChange: e => setSearch(e.target.value) })),
      h('div', { className: 'abp-table-wrap' }, h('table', { className: 'abp-table' }, h('thead', null, h('tr', null, columns.map(c => h('th', { key: c.key }, c.label)), h('th', null, 'Actions'))), h('tbody', null,
        rows.length ? rows.map(row => h('tr', { key: row.id }, columns.map(c => h('td', { key: c.key }, c.render ? c.render(row) : row[c.key])), h('td', { className: 'abp-actions' }, h('button', { onClick: () => request('/' + resource + '/' + row.id).then(setEditing) }, 'Edit'), h('button', { className: 'danger', onClick: () => remove(row) }, deactivate ? 'Deactivate' : 'Delete')))) : h('tr', null, h('td', { colSpan: columns.length + 1, className: 'abp-empty' }, 'No records found.'))))),
      editing && h(EntityDialog, { title: (editing.id ? 'Edit ' : 'Add ') + title.replace(/s$/, ''), fields, value: editing, onClose: () => setEditing(null), onSave: body => request('/' + resource + (editing.id ? '/' + editing.id : ''), { method: editing.id ? 'PUT' : 'POST', data: body }).then(() => { setEditing(null); load(); setNotice({ type: 'success', text: 'Saved successfully.' }); }).catch(e => setNotice({ text: e.message })) })
    );
  }

  function EntityDialog({ title, fields, value, onClose, onSave }) {
    const [form, setForm] = useState(value); const change = (key, val) => setForm(Object.assign({}, form, { [key]: val }));
    return h('div', { className: 'abp-modal-backdrop', onMouseDown: e => e.target === e.currentTarget && onClose() }, h('form', { className: 'abp-modal', onSubmit: e => { e.preventDefault(); onSave(form); } },
      h('header', null, h('h2', null, title), h('button', { type: 'button', onClick: onClose, 'aria-label': 'Close' }, '×')),
      h('div', { className: 'abp-form-grid' }, fields.map(f => f.type === 'textarea' ? h('label', { className: 'abp-field abp-wide', key: f.key }, h('span', null, f.label), h('textarea', { value: form[f.key] || '', onChange: e => change(f.key, e.target.value) })) : f.type === 'select' ? h('div', { key: f.key }, select(f.label, form[f.key], v => change(f.key, v), f.options)) : f.type === 'checkbox' ? h('label', { className: 'abp-check', key: f.key }, h('input', { type: 'checkbox', checked: Boolean(Number(form[f.key])), onChange: e => change(f.key, e.target.checked ? 1 : 0) }), f.label) : f.type === 'media' ? h(MediaField, { key: f.key, label: f.label, value: form[f.key], onChange: v => change(f.key, v) }) : h('div', { key: f.key }, input(f.label, form[f.key], v => change(f.key, v), f.type, { required: f.required, min: f.min, step: f.step })) )),
      h('footer', null, h('button', { type: 'button', onClick: onClose }, 'Cancel'), h('button', { className: 'abp-primary', type: 'submit' }, 'Save'))
    ));
  }

  function Catalog() {
    const [tab, setTab] = useState('services'); const [categories, setCategories] = useState([]);
    useEffect(() => { request('/categories').then(setCategories); }, [tab]);
    const categoryOptions = categories.map(c => ({ value: c.id, label: c.name }));
    return h('div', null, h('div', { className: 'abp-tabs' }, h('button', { className: tab === 'services' ? 'active' : '', onClick: () => setTab('services') }, 'Services'), h('button', { className: tab === 'categories' ? 'active' : '', onClick: () => setTab('categories') }, 'Categories')),
      tab === 'categories' ? h(SimpleCrud, { resource: 'categories', title: 'Categories', fields: [{ key: 'name', label: 'Name', required: true }, { key: 'description', label: 'Description', type: 'textarea' }, { key: 'active', label: 'Active', type: 'checkbox' }], columns: [{ key: 'name', label: 'Name' }, { key: 'description', label: 'Description' }, { key: 'active', label: 'Status', render: r => Number(r.active) ? 'Active' : 'Inactive' }] }) : h(SimpleCrud, { resource: 'services', title: 'Services', deactivate: true, fields: [{ key: 'name', label: 'Name', required: true }, { key: 'category_id', label: 'Category', type: 'select', options: categoryOptions }, { key: 'description', label: 'Description', type: 'textarea' }, { key: 'color', label: 'Color', type: 'color' }, { key: 'price', label: 'Price', type: 'number', min: 0, step: '.01' }, { key: 'duration', label: 'Duration (minutes)', type: 'number', min: 1, required: true }, { key: 'image_id', label: 'Image', type: 'media' }, { key: 'active', label: 'Active', type: 'checkbox' }], columns: [{ key: 'name', label: 'Service' }, { key: 'category_name', label: 'Category' }, { key: 'duration', label: 'Duration', render: r => r.duration + ' min' }, { key: 'price', label: 'Price' }, { key: 'active', label: 'Status', render: r => Number(r.active) ? 'Active' : 'Inactive' }] })
    );
  }

  function DoctorEditor({ value, services, onClose, onSaved }) {
    const [form, setForm] = useState(Object.assign({ active: 1, services: [], working_hours: [], breaks: [], exceptions: [] }, value)); const [error, setError] = useState('');
    const set = (key, val) => setForm(Object.assign({}, form, { [key]: val })); const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const toggleDay = (day, checked) => { const rest = form.working_hours.filter(r => Number(r.weekday) !== day); set('working_hours', checked ? rest.concat([{ weekday: day, start_time: '09:00', end_time: '17:00' }]) : rest); };
    const changeDay = (day, key, value) => set('working_hours', form.working_hours.map(r => Number(r.weekday) === day ? Object.assign({}, r, { [key]: value }) : r));
    const toggleService = (id, checked) => { const rest = form.services.filter(r => Number(r.service_id) !== Number(id)); set('services', checked ? rest.concat([{ service_id: id, price: '' }]) : rest); };
    const changeServicePrice = (id, price) => set('services', form.services.map(r => Number(r.service_id) === Number(id) ? Object.assign({}, r, { price }) : r));
    const addRow = (key, row) => set(key, form[key].concat([row])); const changeRow = (key, index, field, value) => set(key, form[key].map((r, i) => i === index ? Object.assign({}, r, { [field]: value }) : r)); const removeRow = (key, index) => set(key, form[key].filter((r, i) => i !== index));
    return h('div', { className: 'abp-modal-backdrop' }, h('form', { className: 'abp-modal abp-modal-large', onSubmit: e => { e.preventDefault(); request('/doctors' + (form.id ? '/' + form.id : ''), { method: form.id ? 'PUT' : 'POST', data: form }).then(onSaved).catch(err => setError(err.message)); } },
      h('header', null, h('h2', null, form.id ? 'Edit doctor' : 'Add doctor'), h('button', { type: 'button', onClick: onClose }, '×')), error && h('p', { className: 'abp-error' }, error),
      h('div', { className: 'abp-form-grid' }, input('Name', form.name, v => set('name', v)), input('Email', form.email, v => set('email', v), 'email'), input('Phone', form.phone, v => set('phone', v), 'tel'), h(MediaField, { label: 'Profile image', value: form.image_id, onChange: v => set('image_id', v) }), h('label', { className: 'abp-field abp-wide' }, h('span', null, 'Description / bio'), h('textarea', { value: form.bio || '', onChange: e => set('bio', e.target.value) })), h('label', { className: 'abp-check abp-wide' }, h('input', { type: 'checkbox', checked: Boolean(Number(form.active)), onChange: e => set('active', e.target.checked ? 1 : 0) }), 'Active')),
      h('section', { className: 'abp-editor-section' }, h('h3', null, 'Assigned services & custom price'), services.length ? services.map(service => { const assigned = form.services.find(r => Number(r.service_id) === Number(service.id)); return h('div', { className: 'abp-assignment', key: service.id }, h('label', { className: 'abp-check' }, h('input', { type: 'checkbox', checked: Boolean(assigned), onChange: e => toggleService(service.id, e.target.checked) }), service.name), assigned && input('Doctor price', assigned.price, v => changeServicePrice(service.id, v), 'number', { min: 0, step: '.01' })); }) : h('p', { className: 'abp-empty' }, 'Create a service first.')),
      h('section', { className: 'abp-editor-section' }, h('h3', null, 'Weekly working hours'), days.map((name, day) => { const row = form.working_hours.find(r => Number(r.weekday) === day); return h('div', { className: 'abp-schedule-row', key: day }, h('label', { className: 'abp-check' }, h('input', { type: 'checkbox', checked: Boolean(row), onChange: e => toggleDay(day, e.target.checked) }), name), row && h('input', { type: 'time', value: String(row.start_time).slice(0, 5), onChange: e => changeDay(day, 'start_time', e.target.value) }), row && h('span', null, 'to'), row && h('input', { type: 'time', value: String(row.end_time).slice(0, 5), onChange: e => changeDay(day, 'end_time', e.target.value) })); })),
      h('section', { className: 'abp-editor-section' }, h('div', { className: 'abp-section-head' }, h('h3', null, 'Weekly breaks'), h('button', { type: 'button', onClick: () => addRow('breaks', { weekday: 1, start_time: '12:00', end_time: '13:00' }) }, '+ Break')), form.breaks.map((row, i) => h('div', { className: 'abp-schedule-row', key: i }, h('select', { value: row.weekday, onChange: e => changeRow('breaks', i, 'weekday', e.target.value) }, days.map((d, n) => h('option', { value: n, key: n }, d))), h('input', { type: 'time', value: String(row.start_time).slice(0, 5), onChange: e => changeRow('breaks', i, 'start_time', e.target.value) }), h('span', null, 'to'), h('input', { type: 'time', value: String(row.end_time).slice(0, 5), onChange: e => changeRow('breaks', i, 'end_time', e.target.value) }), h('button', { type: 'button', className: 'danger', onClick: () => removeRow('breaks', i) }, 'Remove')))),
      h('section', { className: 'abp-editor-section' }, h('div', { className: 'abp-section-head' }, h('h3', null, 'Days off & special working days'), h('button', { type: 'button', onClick: () => addRow('exceptions', { exception_date: today(), is_working: 0, label: '' }) }, '+ Exception')), form.exceptions.map((row, i) => h('div', { className: 'abp-exception-row', key: i }, h('input', { type: 'date', value: row.exception_date, onChange: e => changeRow('exceptions', i, 'exception_date', e.target.value) }), h('select', { value: Number(row.is_working), onChange: e => changeRow('exceptions', i, 'is_working', Number(e.target.value)) }, h('option', { value: 0 }, 'Day off'), h('option', { value: 1 }, 'Special working day')), Number(row.is_working) ? h('input', { type: 'time', value: String(row.start_time || '09:00').slice(0, 5), onChange: e => changeRow('exceptions', i, 'start_time', e.target.value) }) : null, Number(row.is_working) ? h('input', { type: 'time', value: String(row.end_time || '17:00').slice(0, 5), onChange: e => changeRow('exceptions', i, 'end_time', e.target.value) }) : null, h('input', { placeholder: 'Label', value: row.label || '', onChange: e => changeRow('exceptions', i, 'label', e.target.value) }), h('button', { type: 'button', className: 'danger', onClick: () => removeRow('exceptions', i) }, 'Remove')))),
      h('footer', null, h('button', { type: 'button', onClick: onClose }, 'Cancel'), h('button', { className: 'abp-primary', type: 'submit' }, 'Save doctor'))));
  }

  function Doctors() {
    const [rows, setRows] = useState([]); const [services, setServices] = useState([]); const [editing, setEditing] = useState(null); const [search, setSearch] = useState(''); const [notice, setNotice] = useState(null);
    const load = () => Promise.all([request('/doctors?search=' + encodeURIComponent(search)), request('/services')]).then(([doctors, allServices]) => { setRows(doctors); setServices(allServices); }).catch(e => setNotice({ text: e.message })); useEffect(load, [search]);
    const edit = row => request('/doctors/' + row.id).then(setEditing); const deactivate = row => window.confirm('Deactivate ' + row.name + '?') && request('/doctors/' + row.id, { method: 'DELETE' }).then(load);
    return h('section', null, h('div', { className: 'abp-page-head' }, h('div', null, h('h1', null, 'Doctors'), h('p', null, 'Team profiles, services, prices, and working schedules.')), h('button', { className: 'abp-primary', onClick: () => setEditing({ active: 1, services: [], working_hours: [1,2,3,4,5].map(weekday => ({ weekday, start_time: '09:00', end_time: '17:00' })), breaks: [], exceptions: [] }) }, '+ Add doctor')), h(Notice, { notice, clear: () => setNotice(null) }), h('div', { className: 'abp-toolbar' }, h('input', { type: 'search', value: search, placeholder: 'Search doctors…', onChange: e => setSearch(e.target.value) })), h('div', { className: 'abp-table-wrap' }, h('table', { className: 'abp-table' }, h('thead', null, h('tr', null, ['Doctor','Email','Phone','Status','Actions'].map(v => h('th', { key: v }, v)))), h('tbody', null, rows.length ? rows.map(row => h('tr', { key: row.id }, h('td', null, row.name), h('td', null, row.email), h('td', null, row.phone), h('td', null, Number(row.active) ? 'Active' : 'Inactive'), h('td', { className: 'abp-actions' }, h('button', { onClick: () => edit(row) }, 'Edit'), h('button', { className: 'danger', onClick: () => deactivate(row) }, 'Deactivate')))) : h('tr', null, h('td', { colSpan: 5, className: 'abp-empty' }, 'No doctors found.'))))), editing && h(DoctorEditor, { value: editing, services, onClose: () => setEditing(null), onSaved: () => { setEditing(null); load(); setNotice({ type: 'success', text: 'Doctor saved.' }); } }));
  }

  function Patients() {
    const fields = [{ key: 'first_name', label: 'First name', required: true }, { key: 'last_name', label: 'Last name', required: true }, { key: 'email', label: 'Email', type: 'email', required: true }, { key: 'phone', label: 'Phone', type: 'tel' }, { key: 'date_of_birth', label: 'Date of birth', type: 'date' }, { key: 'gender', label: 'Gender' }, { key: 'reference', label: 'Patient reference' }, { key: 'image_id', label: 'Profile image', type: 'media' }, { key: 'address', label: 'Address', type: 'textarea' }, { key: 'admin_notes', label: 'Admin notes', type: 'textarea' }, { key: 'active', label: 'Active', type: 'checkbox' }];
    return h(SimpleCrud, { resource: 'patients', title: 'Patients', deactivate: true, fields, columns: [{ key: 'reference', label: 'Patient ID' }, { key: 'first_name', label: 'Patient', render: r => r.first_name + ' ' + r.last_name }, { key: 'email', label: 'Email' }, { key: 'phone', label: 'Phone' }, { key: 'active', label: 'Status', render: r => Number(r.active) ? 'Active' : 'Inactive' }] });
  }

  function AppointmentEditor({ value, onClose, onSaved }) {
    const [form, setForm] = useState(value || { status: 'pending', date: today() }); const [lists, setLists] = useState({ doctors: [], services: [], patients: [] }); const [slots, setSlots] = useState([]); const [error, setError] = useState('');
    useEffect(() => { Promise.all([request('/doctors'), request('/services'), request('/patients')]).then(([doctors, services, patients]) => setLists({ doctors, services, patients })); }, []);
    useEffect(() => { if (form.doctor_id && form.service_id && form.date) request('/availability?doctor_id=' + form.doctor_id + '&service_id=' + form.service_id + '&date=' + form.date + (form.id ? '&exclude_appointment=' + form.id : '')).then(r => setSlots(r.slots)).catch(e => { setSlots([]); setError(e.message); }); }, [form.doctor_id, form.service_id, form.date, form.id]);
    const set = (key, val) => setForm(Object.assign({}, form, { [key]: val }));
    const doctorOptions = lists.doctors.filter(r => Number(r.active)).map(r => ({ value: r.id, label: r.name }));
    const serviceOptions = lists.services.filter(r => Number(r.active)).map(r => ({ value: r.id, label: r.name }));
    const patientOptions = lists.patients.filter(r => Number(r.active)).map(r => ({ value: r.id, label: r.first_name + ' ' + r.last_name }));
    return h('div', { className: 'abp-modal-backdrop' }, h('form', { className: 'abp-modal', onSubmit: e => { e.preventDefault(); setError(''); const body = Object.assign({}, form, { start_at: form.start_at || '' }); delete body.date; request('/appointments' + (form.id ? '/' + form.id : ''), { method: form.id ? 'PUT' : 'POST', data: body }).then(onSaved).catch(err => setError(err.message)); } },
      h('header', null, h('h2', null, form.id ? 'Edit booking' : 'New booking'), h('button', { type: 'button', onClick: onClose }, '×')),
      error && h('p', { className: 'abp-error' }, error), h('div', { className: 'abp-form-grid' },
        select('Doctor', form.doctor_id, v => set('doctor_id', v), doctorOptions), select('Service', form.service_id, v => set('service_id', v), serviceOptions), select('Patient', form.patient_id, v => set('patient_id', v), patientOptions),
        input('Date', form.date || (form.start_at || '').slice(0, 10), v => { setForm(Object.assign({}, form, { date: v, start_at: '' })); }, 'date'),
        select('Available time', form.start_at, v => set('start_at', v), slots.map(v => ({ value: v, label: v.slice(11, 16) }))), select('Status', form.status, v => set('status', v), statuses.map(v => ({ value: v, label: v.replace('-', ' ') }))),
        h('label', { className: 'abp-field abp-wide' }, h('span', null, 'Notes'), h('textarea', { value: form.notes || '', onChange: e => set('notes', e.target.value) }))
      ), h('footer', null, h('button', { type: 'button', onClick: onClose }, 'Cancel'), h('button', { className: 'abp-primary', type: 'submit' }, 'Save booking'))));
  }

  function Bookings() {
    const [rows, setRows] = useState([]); const [editing, setEditing] = useState(null); const [search, setSearch] = useState(''); const [status, setStatus] = useState(''); const [from, setFrom] = useState(''); const [to, setTo] = useState(''); const [notice, setNotice] = useState(null);
    const load = () => request('/appointments?' + new URLSearchParams({ search, status, from, to })).then(setRows).catch(e => setNotice({ text: e.message })); useEffect(load, [search, status, from, to]);
    const edit = row => request('/appointments/' + row.id).then(r => setEditing(Object.assign({}, r, { date: r.start_at.slice(0, 10) })));
    const cancel = row => window.confirm('Cancel this appointment?') && request('/appointments/' + row.id, { method: 'DELETE' }).then(load);
    return h('section', null, h('div', { className: 'abp-page-head' }, h('div', null, h('h1', null, 'Bookings'), h('p', null, 'Search, filter, schedule, and update appointments.')), h('button', { className: 'abp-primary', onClick: () => setEditing({ status: 'pending', date: today() }) }, '+ Add booking')), h(Notice, { notice, clear: () => setNotice(null) }),
      h('div', { className: 'abp-toolbar' }, h('input', { type: 'search', placeholder: 'Search patient, doctor, service…', value: search, onChange: e => setSearch(e.target.value) }), h('select', { value: status, onChange: e => setStatus(e.target.value) }, h('option', { value: '' }, 'All statuses'), statuses.map(v => h('option', { key: v, value: v }, v))), h('input', { type: 'date', value: from, onChange: e => setFrom(e.target.value) }), h('input', { type: 'date', value: to, onChange: e => setTo(e.target.value) })),
      h('div', { className: 'abp-table-wrap' }, h('table', { className: 'abp-table' }, h('thead', null, h('tr', null, ['Date & time', 'Patient', 'Doctor', 'Service', 'Status', 'Actions'].map(v => h('th', { key: v }, v)))), h('tbody', null, rows.length ? rows.map(row => h('tr', { key: row.id }, h('td', null, row.start_at), h('td', null, row.patient_name), h('td', null, row.doctor_name), h('td', null, row.service_name), h('td', null, h('span', { className: 'abp-status ' + row.status }, row.status)), h('td', { className: 'abp-actions' }, h('button', { onClick: () => edit(row) }, 'Edit'), !['cancelled', 'completed'].includes(row.status) && h('button', { className: 'danger', onClick: () => cancel(row) }, 'Cancel')))) : h('tr', null, h('td', { colSpan: 6, className: 'abp-empty' }, 'No bookings found.'))))), editing && h(AppointmentEditor, { value: editing, onClose: () => setEditing(null), onSaved: () => { setEditing(null); load(); } }));
  }

  function Calendar() {
    const [view, setView] = useState('week'); const [focus, setFocus] = useState(today()); const [rows, setRows] = useState([]); const [filters, setFilters] = useState({ doctor_id: '', service_id: '', status: '', search: '' }); const [lists, setLists] = useState({ doctors: [], services: [] }); const [schedules, setSchedules] = useState({}); const [notice, setNotice] = useState(null); const [editing, setEditing] = useState(null);
    const span = view === 'day' ? 0 : view === 'week' || view === 'timeline' ? 6 : 34; const from = view === 'month' ? focus.slice(0, 8) + '01' : focus; const to = dateShift(from, span);
    const load = () => request('/calendar?' + new URLSearchParams(Object.assign({ from, to }, filters))).then(setRows).catch(e => setNotice({ text: e.message })); useEffect(load, [view, focus, filters.doctor_id, filters.service_id, filters.status, filters.search]); useEffect(() => { Promise.all([request('/doctors'), request('/services')]).then(([doctors, services]) => { setLists({ doctors, services }); return Promise.all(doctors.map(doctor => request('/doctors/' + doctor.id))); }).then(details => setSchedules(Object.fromEntries(details.map(doctor => [String(doctor.id), doctor])))); }, []);
    const dates = useMemo(() => { const result = []; for (let i = 0; i <= span; i++) result.push(dateShift(from, i)); return result; }, [from, span]);
    const drop = (e, date) => { e.preventDefault(); const id = e.dataTransfer.getData('text/appointment'); const row = rows.find(r => String(r.id) === id); if (!row) return; const next = date + row.start_at.slice(10); request('/appointments/' + id, { method: 'PUT', data: Object.assign({}, row, { start_at: next }) }).then(load).catch(err => { setNotice({ text: 'Move rejected: ' + err.message }); load(); }); };
    const opt = (list, label) => [h('option', { value: '', key: '' }, label)].concat(list.map(r => h('option', { key: r.id, value: r.id }, r.name)));
    const dayCells = dates.map(date => {
      const schedule = schedules[String(filters.doctor_id)];
      const exception = schedule && schedule.exceptions.find(r => r.exception_date === date);
      const weekday = new Date(date + 'T12:00:00').getDay();
      const hours = schedule ? (exception ? (Number(exception.is_working) ? [exception] : []) : schedule.working_hours.filter(r => Number(r.weekday) === weekday)) : [];
      const breaks = schedule && !exception ? schedule.breaks.filter(r => Number(r.weekday) === weekday) : [];
      const scheduleNote = schedule ? h('div', { className: 'abp-schedule-note ' + (exception && !Number(exception.is_working) ? 'off' : '') }, exception && !Number(exception.is_working) ? (exception.label || 'Day off') : hours.length ? 'Hours ' + hours.map(r => String(r.start_time).slice(0, 5) + '–' + String(r.end_time).slice(0, 5)).join(', ') + (breaks.length ? ' · Break ' + breaks.map(r => String(r.start_time).slice(0, 5) + '–' + String(r.end_time).slice(0, 5)).join(', ') : '') : 'Not working') : null;
      const events = rows.filter(r => r.start_at.slice(0, 10) === date).map(row => h('button', {
        key: row.id, className: 'abp-event', draggable: true,
        onDragStart: e => e.dataTransfer.setData('text/appointment', row.id),
        onClick: () => setEditing(Object.assign({}, row, { date })),
        style: { borderLeftColor: row.color }
      }, h('time', null, row.start_at.slice(11, 16)), h('strong', null, row.patient_name), h('small', null, row.doctor_name + ' · ' + row.service_name)));
      return h('div', { className: 'abp-day', key: date, onDragOver: e => e.preventDefault(), onDrop: e => drop(e, date) },
        h('header', null, h('b', null, new Date(date + 'T12:00:00').toLocaleDateString(undefined, { weekday: 'short' })), h('span', null, date)), scheduleNote, events);
    });
    return h('section', null,
      h('div', { className: 'abp-page-head' }, h('div', null, h('h1', null, 'Calendar'), h('p', null, 'Server-validated scheduling across your team.')), h('button', { className: 'abp-primary', onClick: () => setEditing({ status: 'pending', date: focus }) }, '+ Appointment')),
      h(Notice, { notice, clear: () => setNotice(null) }),
      h('div', { className: 'abp-calendar-tools' }, h('button', { onClick: () => setFocus(dateShift(focus, view === 'month' ? -30 : -1)) }, '‹'), h('input', { type: 'date', value: focus, onChange: e => setFocus(e.target.value) }), h('button', { onClick: () => setFocus(dateShift(focus, view === 'month' ? 30 : 1)) }, '›'), h('div', { className: 'abp-segmented' }, ['timeline', 'day', 'week', 'month'].map(v => h('button', { key: v, className: view === v ? 'active' : '', onClick: () => setView(v) }, v))), h('select', { value: filters.doctor_id, onChange: e => setFilters(Object.assign({}, filters, { doctor_id: e.target.value })) }, opt(lists.doctors, 'All doctors')), h('select', { value: filters.service_id, onChange: e => setFilters(Object.assign({}, filters, { service_id: e.target.value })) }, opt(lists.services, 'All services')), h('select', { value: filters.status, onChange: e => setFilters(Object.assign({}, filters, { status: e.target.value })) }, opt(statuses.map(name => ({ id: name, name })), 'All statuses')), h('input', { type: 'search', value: filters.search, placeholder: 'Search…', onChange: e => setFilters(Object.assign({}, filters, { search: e.target.value })) })),
      h('div', { className: 'abp-calendar ' + view }, dayCells),
      editing && h(AppointmentEditor, { value: editing, onClose: () => setEditing(null), onSaved: () => { setEditing(null); load(); } })
    );
  }

  function Settings() {
    const [form, setForm] = useState(null); const [notice, setNotice] = useState(null); useEffect(() => { request('/settings').then(setForm); }, []); if (!form) return h('p', null, 'Loading settings…'); const set = (key, value) => setForm(Object.assign({}, form, { [key]: value }));
    return h('section', null, h('div', { className: 'abp-page-head' }, h('div', null, h('h1', null, 'Settings'), h('p', null, 'Business details and global scheduling preferences.'))), h(Notice, { notice, clear: () => setNotice(null) }), h('form', { className: 'abp-panel abp-settings', onSubmit: e => { e.preventDefault(); request('/settings', { method: 'PUT', data: form }).then(setForm).then(() => setNotice({ type: 'success', text: 'Settings saved.' })).catch(err => setNotice({ text: err.message })); } }, h('div', { className: 'abp-form-grid' }, input('Business name', form.business_name, v => set('business_name', v)), input('Business email', form.business_email, v => set('business_email', v), 'email'), input('Business phone', form.business_phone, v => set('business_phone', v), 'tel'), input('Currency', form.currency, v => set('currency', v)), input('Date format', form.date_format, v => set('date_format', v)), input('Time format', form.time_format, v => set('time_format', v)), input('First day of week (0–6)', form.first_day_of_week, v => set('first_day_of_week', v), 'number', { min: 0, max: 6 }), input('Slot interval (minutes)', form.slot_interval, v => set('slot_interval', v), 'number', { min: 5, max: 120 }), select('Default appointment status', form.default_status, v => set('default_status', v), [{ value: 'pending', label: 'Pending' }, { value: 'approved', label: 'Approved' }]), h('label', { className: 'abp-field abp-wide' }, h('span', null, 'Business address'), h('textarea', { value: form.business_address, onChange: e => set('business_address', e.target.value) }))), h('button', { className: 'abp-primary' }, 'Save settings')));
  }

  function Notifications() {
    const [rows, setRows] = useState([]); const [error, setError] = useState(''); useEffect(() => { request('/notifications').then(setRows).catch(e => setError(e.message)); }, []);
    return h('section', null, h('div', { className: 'abp-page-head' }, h('div', null, h('h1', null, 'Notifications'), h('p', null, 'Recent transactional email delivery attempts.'))), error && h('p', { className: 'abp-error' }, error), h('div', { className: 'abp-table-wrap' }, h('table', { className: 'abp-table' }, h('thead', null, h('tr', null, ['Sent at','Event','Recipient','Subject','Status'].map(v => h('th', { key: v }, v)))), h('tbody', null, rows.length ? rows.map(row => h('tr', { key: row.id }, h('td', null, row.created_at), h('td', null, row.event.replaceAll('_', ' ')), h('td', null, row.recipient), h('td', null, row.subject), h('td', null, h('span', { className: 'abp-status ' + (row.status === 'sent' ? 'approved' : 'rejected'), title: row.error || '' }, row.status)))) : h('tr', null, h('td', { colSpan: 5, className: 'abp-empty' }, 'No email attempts yet.'))))));
  }

  function Placeholder({ title, text }) { return h('section', null, h('div', { className: 'abp-page-head' }, h('div', null, h('h1', null, title), h('p', null, text))), h('div', { className: 'abp-panel abp-empty' }, 'This navigation area is reserved; no post–Phase 1 functionality has been added.')); }
  function App() {
    const [page, setPage] = useState('dashboard'); const content = { dashboard: h(Dashboard), calendar: h(Calendar), bookings: h(Bookings), doctors: h(Doctors), catalog: h(Catalog), patients: h(Patients), notifications: h(Notifications), customize: h(Placeholder, { title: 'Customize', text: 'Customization shell.' }), fields: h(Placeholder, { title: 'Custom Fields', text: 'Custom-field shell.' }), integrations: h(Placeholder, { title: 'Features & Integrations', text: 'Integration-ready shell; external calendars are outside Phase 1.' }), settings: h(Settings) };
    return h('div', { className: 'abp-app' }, h('aside', null, h('div', { className: 'abp-brand' }, h('span', null, 'A'), h('div', null, h('b', null, 'Appointment'), h('small', null, 'Booking workspace'))), h('nav', { 'aria-label': 'Appointment sections' }, pages.map(([id, label, icon]) => h('button', { key: id, className: page === id ? 'active' : '', onClick: () => setPage(id) }, h('i', { className: 'dashicons dashicons-' + icon }), h('span', null, label))))), h('main', null, content[page]));
  }
  wp.element.createRoot(document.getElementById('abp-admin-app')).render(h(App));
})(window.wp);
