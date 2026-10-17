// Compare persisted block content and editor identities without serializing
// RichTextData's cyclic formatting caches. Also normalizes legacy block markup.
export function editorBlockSnapshot(wp = window.wp) {
	const blocks = wp.data.select('core/block-editor').getBlocks();
	const identity = (block) => ({
		clientId: block.clientId,
		name: block.name,
		innerBlocks: (block.innerBlocks || []).map(identity),
	});
	return { serialized: wp.blocks.serialize(blocks), identities: blocks.map(identity) };
}
