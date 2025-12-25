import React, { useEffect, useState } from 'react';

export default function App() {
  const [articles, setArticles] = useState([]);
  const [loading, setLoading] = useState(true);
  const API = 'http://127.0.0.1:8000/api';

  useEffect(() => {
    async function load() {
      try {
        const res = await fetch(`${API}/articles`);
        const json = await res.json();
        if (json.success) setArticles(json.data || []);
      } catch (e) {
        console.error('Failed to load articles', e);
      } finally {
        setLoading(false);
      }
    }

    load();
  }, []);

  return (
    <div style={{ padding: '1rem', fontFamily: 'Arial, sans-serif' }}>
      <h1>Beyond Chats – React Frontend</h1>

      {loading && <p>Loading articles…</p>}

      {!loading && articles.length === 0 && <p>No articles found.</p>}

      <ul>
        {articles.map((a) => (
          <li key={a.id} style={{ marginBottom: '1rem' }}>
            <strong>{a.title}</strong>
            <div style={{ fontSize: '0.9rem', color: '#444' }}>
              <p><em>Status:</em> {a.status}</p>
              {a.generated_content ? (
                <div>
                  <p><em>Generated from:</em> {a.generated_from_id}</p>
                  <pre style={{ whiteSpace: 'pre-wrap', background: '#f6f6f6', padding: '0.5rem' }}>{a.generated_content}</pre>
                </div>
              ) : (
                <div>
                  <p><em>Original content:</em></p>
                  <pre style={{ whiteSpace: 'pre-wrap', background: '#f6f6f6', padding: '0.5rem' }}>{a.original_content}</pre>
                </div>
              )}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
